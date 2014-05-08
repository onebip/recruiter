<?php

namespace Recruiter;

use MongoDB;
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

    public function __construct($status, Recruiter $recruiter, MongoDB $db)
    {
        $this->db = $db;
        $this->status = $status;
        $this->recruiter = $recruiter;
        $this->scheduled = $db->selectCollection('scheduled');
        $this->roster = $db->selectCollection('roster');
    }

    public function work()
    {
    }

    private function workToDo()
    {
    }

    private function availableToWork()
    {
        $this->status['available'] = true;
        $this->status['available_since'] = new MongoDate();
    }

    private function update()
    {
        $this->roster->save($this->status);
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
