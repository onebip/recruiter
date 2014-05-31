<?php

namespace Recruiter;

use Timeless;
use Recruiter\RetryPolicy;

class JobToSchedule
{
    private $job;
    private $scheduledAt;
    private $retryPolicy;

    public function __construct($job)
    {
        $this->job = $job;
    }

    public function retryWithPolicy(RetryPolicy $retryPolicy)
    {
        $this->retryPolicy = $retryPolicy;
        return $this;
    }

    public function doNotRetry()
    {
        return $this->retryWithPolicy(new RetryPolicy\DoNotDoItAgain());
    }

    public function inBackground()
    {
        $this->scheduledAt = Timeless\clock()->now();
        return $this;
    }

    public function execute()
    {
        if ($this->hasRetryPolicy()) {
            $this->job->retryWithPolicy($this->retryPolicy);
        }
        $this->job->scheduleAt($this->scheduledAt());
        if (!$this->isScheduled()) {
            $this->job->execute();
        }
    }

    private function hasRetryPolicy()
    {
        return !is_null($this->retryPolicy);
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
