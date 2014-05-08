<?php

namespace Recruiter;

use MongoDate;
use MongoCollection;

class Worker
{
    private $status;
    private $recruiter;
    private $roster;

    public static function workFor(Recruiter $recruiter)
    {
        return new self(self::initialize(), $recruiter);
    }

    public function __construct($status, Recruiter $recruiter)
    {
        $this->status = $status;
        $this->recruiter = $recruiter;
        $recruiter->hire($this);
    }

    public function addTo(MongoCollection $roster)
    {
        $this->roster = $roster;
        $this->update();
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
