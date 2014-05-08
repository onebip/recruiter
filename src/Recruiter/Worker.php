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

    public static function load($id, Recruiter $recruiter, MongoDB $db)
    {
        return self::import(
            $db->selectCollection('roster')->findOne(['_id' => $id]), $recruiter, $db
        );
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
        $job->execute();
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
            'working' => false,
            'pid' => getmypid()
        ];
    }
}
