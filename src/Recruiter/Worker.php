<?php

namespace Recruiter;

use MongoId;
use MongoCollection;
use Timeless as T;
use Recruiter\Worker\Repository;
use Onebip;
use Onebip\Clock;
use DateInterval;

class Worker
{
    private $status;
    private $recruiter;
    private $repository;

    public static function workFor(Recruiter $recruiter, Repository $repository)
    {
        $worker = new self(self::initialize(), $recruiter, $repository);
        $worker->save();
        return $worker;
    }

    public static function import($document, Recruiter $recruiter, Repository $repository)
    {
        return new self(
            self::fromMongoDocumentToInternalStatus($document),
            $recruiter, $repository
        );
    }

    public function __construct($status, Recruiter $recruiter, Repository $repository)
    {
        $this->status = $status;
        $this->recruiter = $recruiter;
        $this->repository = $repository;
    }

    public function id()
    {
        return $this->status['_id'];
    }

    public function pid()
    {
        return $this->status['pid'];
    }

    public function work()
    {
        $this->refresh();
        if ($this->hasBeenAssignedToDoSomething()) {
            $this->workOn(
                $job = $this->recruiter->scheduledJob(
                    $this->status['assigned_to'][(string)$this->status['_id']]
                )
            );
            return (string) $job->id();
        } else {
            $this->stillHere();
            return false;
        }
    }

    public function export()
    {
        return $this->status;
    }

    public function updateWith($document)
    {
        $this->status = self::fromMongoDocumentToInternalStatus($document);
    }

    public function workOnJobsTaggedAs($tag)
    {
        $this->status['work_on'] = $tag;
        $this->save();
    }

    public function retire()
    {
        if ($this->hasBeenAssignedToDoSomething()) {
            throw new CannotRetireWorkerAtWorkException();
        }
        $this->repository->retireWorkerWithId($this->status['_id']);
    }

    private function stillHere()
    {
        $lastSeenAt = T\MongoDate::now();
        $this->status['last_seen_at'] = $lastSeenAt;
        $this->repository->atomicUpdate($this, ['last_seen_at' => $lastSeenAt]);
    }

    private function workOn($job)
    {
        $this->beforeExecutionOf($job);
        $job->execute();
        $this->afterExecutionOf($job);
    }

    private function beforeExecutionOf($job)
    {
        $this->status['working'] = true;
        $this->status['working_on'] = $job->id();
        $this->status['working_since'] = T\MongoDate::now();
        $this->status['last_seen_at'] = T\MongoDate::now();
        $this->save();
    }

    private function afterExecutionOf($job)
    {
        $this->status['working'] = false;
        $this->status['available'] = true;
        $this->status['available_since'] = T\MongoDate::now();
        $this->status['last_seen_at'] = T\MongoDate::now();
        unset($this->status['working_on']);
        unset($this->status['working_since']);
        unset($this->status['assigned_to']);
        unset($this->status['assigned_since']);
        $this->save();
    }

    private function hasBeenAssignedToDoSomething()
    {
        if (is_null($this->status)) {
            // I don't know yet why this happens, but seems like that sometimes
            // some workers remains zombies and they have $this->status === null
            // this is very strange, I need to dig deeper but for now the only
            // thing to do seems like terminate the process
            exit(1);
        }
        return array_key_exists('assigned_to', $this->status);
    }

    private function refresh()
    {
        $this->repository->refresh($this);
    }

    private function save()
    {
        $this->repository->save($this);
    }

    private static function fromMongoDocumentToInternalStatus($document)
    {
        return $document;
    }

    private static function initialize()
    {
        return [
            '_id' => new MongoId(),
            'work_on' => '*',
            'available' => true,
            'available_since' => T\MongoDate::now(),
            'last_seen_at' => T\MongoDate::now(),
            'created_at' => T\MongoDate::now(),
            'working' => false,
            'pid' => getmypid()
        ];
    }

    public static function canWorkOnAnyJobs($worksOn)
    {
        return $worksOn === '*';
    }

    public static function pickAvailableWorkers(MongoCollection $collection, $workersPerUnit)
    {
        $result = [];
        $workers = iterator_to_array($collection->find(['available' => true], ['_id' => 1, 'work_on' => 1]));
        if (count($workers) > 0) {
            $unitsOfWorkers = Onebip\array_group_by(
                $workers,
                function($worker) {return $worker['work_on'];}
            );
            foreach ($unitsOfWorkers as $workOn => $workersInUnit) {
                $workersInUnit = Onebip\array_pluck($workersInUnit, '_id');
                $workersInUnit = array_slice($workersInUnit, 0, min(count($workersInUnit), $workersPerUnit));
                $result[] = [$workOn, $workersInUnit];
            }
        }
        return $result;
    }

    public static function assignJobsToWorkers(MongoCollection $collection, $jobs, $workers)
    {
        $assignment = array_combine(
            Onebip\array_map($workers, function($id) {return (string)$id;}),
            $jobs
        );
        $result = $collection->update(
            $where = ['_id' => ['$in' => array_values($workers)]],
            $update = ['$set' => [
                'available' => false,
                'assigned_to' => $assignment,
                'assigned_since' => T\MongoDate::now()
            ]],
            ['multiple' => true]
        );
        return $assignment;
    }

    public static function assignedJobs(MongoCollection $collection)
    {
        $cursor = $collection->find([], ['assigned_to' => 1]);
        $jobs = [];
        foreach ($cursor as $document) {
            if (array_key_exists('assigned_to', $document)) {
                $jobs = array_merge($jobs, array_values($document['assigned_to']));
            }
        }
        return array_unique($jobs);
    }

    public static function retireDeadWorkers(Repository $roster, Clock $clock)
    {
        $now = $clock->current();
        $consideredDeadAfter = new DateInterval("PT30M");
        $consideredDeadAt = clone $now;
        $consideredDeadAt->sub($consideredDeadAfter);
        $deadWorkers = $roster->deadWorkers($consideredDeadAt);
        $jobsToReassign = [];
        foreach ($deadWorkers as $deadWorker) {
            $roster->retireWorkerWithId($deadWorker['_id']);
            if (array_key_exists('assigned_to', $deadWorker)) {
                if (array_key_exists((string)$deadWorker['_id'], $deadWorker['assigned_to'])) {
                    $jobsToReassign[] = $deadWorker['assigned_to'][(string)$deadWorker['_id']];
                }
            }
        }
        return $jobsToReassign;
    }
}
