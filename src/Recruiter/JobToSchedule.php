<?php

namespace Recruiter;

use Timeless;
use Recruiter\RetryPolicy;

class JobToSchedule
{
    private $job;
    private $mustBeScheduled;

    public function __construct($job)
    {
        $this->job = $job;
        $this->mustBeScheduled = false;
    }

    public function retryWithPolicy(RetryPolicy $retryPolicy)
    {
        $this->job->retryWithPolicy($retryPolicy);
        return $this;
    }

    public function doNotRetry()
    {
        $this->job->retryWithPolicy(new RetryPolicy\DoNotDoItAgain());
        return $this;
    }

    public function inBackground()
    {
        $this->mustBeScheduled = true;
        $this->job->scheduleAt(Timeless\clock()->now()->to('MongoDate'));
        return $this;
    }

    public function execute()
    {
        if ($this->mustBeScheduled) {
            $this->job->schedule();
        } else {
            $this->job->execute();
        }
    }
}
