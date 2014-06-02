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

    public function scheduleAt(Moment $at)
    {
        $this->hasBeenScheduled = true;
        $this->job->scheduleAt($at->to('MongoDate'));
        $this->job->save();
    }

    public function scheduleIn(Duration $in)
    {
        $this->hasBeenScheduled = true;
        $this->job->scheduleAt($in->fromNow()->to('MongoDate'));
        $this->job->save();
    }

    public function archive($why = null)
    {
        $this->hasBeenArchived = true;
        $this->job->archive($why);
    }

    public function causeOfFailure()
    {
        // TODO: check to see if it's not null otherwise raise exception
        return $this->lastJobExecution->causeOfFailure();
    }

    public function numberOfAttempts()
    {
        return $this->job->numberOfAttempts();
    }

    public function archiveIfNotScheduled()
    {
        if (!$this->hasBeenScheduled && !$this->hasBeenArchived) {
            // TODO $this->job->archive('not-scheduled-by-retry-policy');
            $this->job->archive(false);
        }
    }
}
