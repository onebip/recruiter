<?php

namespace Recruiter;

use Timeless as T;
use Timeless\Interval;
use Timeless\Moment;
use Recruiter\RetryPolicy;
use Symfony\Component\EventDispatcher\EventDispatcher;

class JobToSchedule
{
    private $job;
    private $mustBeScheduled;

    public function __construct($job)
    {
        $this->job = $job;
        $this->mustBeScheduled = false;
    }

    public function doNotRetry()
    {
        return $this->retryWithPolicy(new RetryPolicy\DoNotDoItAgain());
    }

    public function retryManyTimes($howManyTimes, Interval $timeToWaitBeforeRetry, $retriableExceptionTypes = [])
    {
        $this->job->retryWithPolicy(
            $this->filterForRetriableExceptions(
                new RetryPolicy\RetryManyTimes($howManyTimes, $timeToWaitBeforeRetry),
                $retriableExceptionTypes
            )
        );
        return $this;
    }

    public function retryWithPolicy(RetryPolicy $retryPolicy, $retriableExceptionTypes = [])
    {
        $this->job->retryWithPolicy(
            $this->filterForRetriableExceptions(
                $retryPolicy, $retriableExceptionTypes
            )
        );
        return $this;
    }

    public function inBackground()
    {
        return $this->scheduleAt(T\now());
    }

    public function scheduleIn(Interval $duration)
    {
        return $this->scheduleAt($duration->fromNow());
    }

    public function scheduleAt(Moment $momentInTime)
    {
        $this->mustBeScheduled = true;
        $this->job->scheduleAt($momentInTime);
        return $this;
    }

    public function inGroup($group)
    {
        if (!empty($group)) {
            $this->job->inGroup($group);
        }

        return $this;
    }

    public function taggedAs($tags)
    {
        if (!empty($tags)) {
            $this->job->taggedAs(is_array($tags) ? $tags : [$tags]);
        }

        return $this;
    }

    public function execute()
    {
        if ($this->mustBeScheduled) {
            $this->job->save();
        } else {
            //
            $this->job->execute($this->emptyEventDispatcher());
        }
        return (string) $this->job->id();
    }

    private function emptyEventDispatcher()
    {
        return new EventDispatcher();
    }

    public function __call($name, $arguments)
    {
        $this->job->methodToCallOnWorkable($name);
        $this->execute();
    }

    private function filterForRetriableExceptions($retryPolicy, $retriableExceptionTypes)
    {
        if (!is_array($retriableExceptionTypes)) {
            $retriableExceptionTypes = [$retriableExceptionTypes];
        }
        if (!empty($retriableExceptionTypes)) {
            $retryPolicy = new RetryPolicy\RetriableExceptionFilter($retryPolicy, $retriableExceptionTypes);
        }
        return $retryPolicy;
    }
}
