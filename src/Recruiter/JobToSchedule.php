<?php

namespace Recruiter;

use Timeless;

class JobToSchedule
{
    private $job;
    private $scheduledAt;

    public function __construct($job)
    {
        $this->job = $job;
    }

    public function inBackground()
    {
        $this->scheduledAt = Timeless\clock()->now();
        return $this;
    }

    public function execute()
    {
        $this->job->scheduleAt($this->scheduledAt());
        if (!$this->isScheduled()) {
            $this->job->execute();
        }
    }

    private function isScheduled()
    {
        return !is_null($this->scheduledAt);
    }

    private function scheduledAt()
    {
        if ($this->scheduledAt) {
            return $this->scheduledAt->to('MongoDate');
        }
        return Timeless\clock()->now()->to('MongoDate');
    }
}
