<?php

namespace Recruiter;

use Timeless\Moment;
use Timeless\Duration;

class JobAfterFailure
{
    private $job;
    private $lastJobExecution;
    private $hasBeenScheduled;
    private $hasBeenArchived;

    public function __construct(Job $job, JobExecution $lastJobExecution)
    {
        $this->job = $job;
        $this->lastJobExecution = $lastJobExecution;
        $this->hasBeenScheduled = false;
        $this->hasBeenArchived = false;
    }

    public function scheduleWith(RetryPolicy $retryPolicy)
    {
        if ($retryPolicy->isExceptionRetriable($this->lastJobExecution->causeOfFailure())) {
            $retryPolicy->schedule($this);
        } else {
            $this->job->archive('non-retriable-exception');
        }
    }

    public function scheduleAt(Moment $at)
    {
        $this->hasBeenScheduled = true;
        $this->job->scheduleAt($at);
        $this->job->save();
    }

    public function scheduleIn(Duration $in)
    {
        $this->hasBeenScheduled = true;
        $this->job->scheduleAt($in->fromNow());
        $this->job->save();
    }

    public function archive($why)
    {
        $this->hasBeenArchived = true;
        $this->job->archive($why);
    }

    public function causeOfFailure()
    {
        return $this->lastJobExecution->causeOfFailure();
    }

    public function numberOfAttempts()
    {
        return $this->job->numberOfAttempts();
    }

    public function archiveIfNotScheduled()
    {
        if (!$this->hasBeenScheduled && !$this->hasBeenArchived) {
            $this->job->archive('not-scheduled-by-retry-policy');
        }
    }
}
