<?php

namespace Recruiter;

use MongoDB;
use MongoId;
use MongoDate;
use Recruiter\Worker\Repository;

class Worker
{
    private $db;
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

    public function assignedTo($job)
    {
        $this->status['available'] = false;
        $this->status['assigned_to'] = $job->id();
        $this->status['assigned_since'] = new MongoDate();
        unset($this->status['available_since']);
        $this->save();
    }

    public function work()
    {
        $this->refresh();
        if ($this->hasBeenAssignedToDoSomething()) {
            $this->workOn(
                $this->recruiter->scheduledJob($this->status['assigned_to'])
            );
            return true;
        }
        return false;
    }

    public function workOn($job)
    {
        $this->beforeExecutionOf($job);
        $job->execute();
        $this->afterExecutionOf($job);
    }

    public function export()
    {
        return $this->status;
    }

    public function updateWith($document)
    {
        $this->status = self::fromMongoDocumentToInternalStatus($document);
    }

    private function beforeExecutionOf($job)
    {
        $this->status['working'] = true;
        $this->status['working_on'] = $job->id();
        $this->status['working_since'] = new MongoDate();
        $this->save();
    }

    private function afterExecutionOf($job)
    {
        $this->status['working'] = false;
        $this->status['available'] = true;
        $this->status['available_since'] = new MongoDate();
        unset($this->status['working_on']);
        unset($this->status['working_since']);
        unset($this->status['assigned_to']);
        unset($this->status['assigned_since']);
        $this->save();
    }

    private function hasBeenAssignedToDoSomething()
    {
        return array_key_exists('assigned_to', $this->status);
    }

    private function availableToWork()
    {
        $this->status['available'] = true;
        $this->status['available_since'] = new MongoDate();
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
            'available' => true,
            'available_since' => new MongoDate(),
            'created_at' => new MongoDate(),
            'working' => false,
            'pid' => getmypid()
        ];
    }
}
