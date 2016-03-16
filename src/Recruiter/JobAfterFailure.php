<?php

namespace Recruiter;

use Timeless\Moment;
use Timeless\Interval;

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

    public function scheduleIn(Interval $in)
    {
        $this->scheduleAt($in->fromNow());
    }

    public function scheduleAt(Moment $at)
    {
        $this->hasBeenScheduled = true;
        $this->job->scheduleAt($at);
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

    /**
     * @return boolean
     */
    public function archiveIfNotScheduled()
    {
        if (!$this->hasBeenScheduled && !$this->hasBeenArchived) {
            $this->job->archive('not-scheduled-by-retry-policy');
            return true;
        }
        return false;
    }
}
