<?php

namespace Recruiter;

use MongoDB;
use MongoId;
use MongoDate;

class Worker
{
    private $db;
    private $status;
    private $recruiter;
    private $scheduled;
    private $roster;

    public static function workFor(Recruiter $recruiter, MongoDB $db)
    {
        $worker = new self(self::initialize(), $recruiter, $db);
        $worker->update();
        return $worker;
    }

    public static function import($document, Recruiter $recruiter, MongoDB $db)
    {
        return new self(self::fromMongoDocumentToInternalStatus($document), $recruiter, $db);
    }

    public function __construct($status, Recruiter $recruiter, MongoDB $db)
    {
        $this->db = $db;
        $this->status = $status;
        $this->recruiter = $recruiter;
        $this->scheduled = $db->selectCollection('scheduled');
        $this->roster = $db->selectCollection('roster');
    }

    public function workToDo()
    {
        $jobs = [];
        $this->refresh();
        if ($this->hasBeenAssignedToDoSomething()) {
            $jobs[] = Job::import(
                $this->scheduled->findOne(['_id' => $this->status['assigned_to']]),
                $this->recruiter
            );
        }
        return $jobs;
    }

    public function workOn($job)
    {
        $this->beforeExecutionOf($job);
        $job->execute();
        $this->afterExecutionOf($job);
    }

    private function beforeExecutionOf($job)
    {
        $this->status['working'] = true;
        $this->status['working_on'] = $job->id();
        $this->status['working_since'] = new MongoDate();
        $this->update();
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
        $this->update();
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
        $this->status = self::fromMongoDocumentToInternalStatus(
            $this->roster->findOne(['_id' => $this->status['_id']])
        );
    }

    private function update()
    {
        $this->roster->save($this->status);
    }

    private static function fromMongoDocumentToInternalStatus($document)
    {
        return $document;
    }

    private static function initialize()
    {
        return [
            'available' => true,
            'available_since' => new MongoDate(),
            'created_at' => new MongoDate(),
            'working' => false,
            'pid' => getmypid()
        ];
    }
}
