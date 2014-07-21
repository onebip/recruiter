<?php

namespace Recruiter;

use MongoId;
use MongoCollection;

use Timeless as T;
use Underscore\Underscore as _;

use Recruiter\Worker\Repository;

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
                $this->recruiter->scheduledJob(
                    $this->status['assigned_to'][(string)$this->status['_id']]
                )
            );
            return true;
        }
        return false;
    }

    public function export()
    {
        return $this->status;
    }

    public function updateWith($document)
    {
        $this->status = self::fromMongoDocumentToInternalStatus($document);
    }

    public function workOnJobsTaggedAS($tag)
    {
        $this->status['work_on'] = $tag;
        $this->save();
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
        $this->save();
    }

    private function afterExecutionOf($job)
    {
        $this->status['working'] = false;
        $this->status['available'] = true;
        $this->status['available_since'] = T\MongoDate::now();
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
            'created_at' => T\MongoDate::now(),
            'working' => false,
            'pid' => getmypid()
        ];
    }

    public static function canWorkOnAnyJobs($worksOn)
    {
        return $worksOn === '*';
    }

    public static function pickAvailableWorkers(MongoCollection $collection, $workersPerUnit, $callback)
    {
        $numberOfWorkersWithJobs = 0;
        $workers = iterator_to_array($collection->find(['available' => true], ['_id' => 1, 'work_on' => 1]));
        if (count($workers) > 0) {
            $unitsOfWorkers = _::group($workers, function($worker) {return $worker['work_on'];});
            foreach ($unitsOfWorkers as $workOn => $workersInUnit) {
                $workersInUnit = _::pluck($workersInUnit, '_id');
                $workersInUnit = array_slice($workersInUnit, 0, min(count($workersInUnit), $workersPerUnit));
                $numberOfWorkersWithJobs += $callback($workOn, $workersInUnit);
            }
        }
        return $numberOfWorkersWithJobs;
    }

    public static function assignJobsToWorkers(MongoCollection $collection, $jobs, $workers)
    {
        $collection->update(
            ['_id' => ['$in' => array_values($workers)]],
            ['$set' => [
                'available' => false,
                'assigned_to' => array_combine(
                        _::transform($workers, function($id) {return (string)$id;}),
                        $jobs
                ),
                'assigned_since' => T\MongoDate::now()
            ]],
            ['multiple' => true]
        );
    }
}
