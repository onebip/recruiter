<?php

namespace Recruiter;

use Timeless;
use Timeless\Duration;
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

    public function doNotRetry()
    {
        return $this->retryWithPolicy(new RetryPolicy\DoNotDoItAgain());
    }

    public function retryManyTimes($howManyTimes, Duration $timeToWaitBeforeRetry, $retriableExceptionTypes = [])
    {
        return $this->retryWithPolicy(
            $this->filterForRetriableExceptions(
                new RetryPolicy\RetryManyTimes($howManyTimes, $timeToWaitBeforeRetry->seconds()),
                $retriableExceptionTypes
            )
        );
    }

    public function retryWithPolicy(RetryPolicy $retryPolicy)
    {
        $this->job->retryWithPolicy($retryPolicy);
        return $this;
    }

    public function inBackground()
    {
        $this->mustBeScheduled = true;
        $this->job->scheduleAt(Timeless\MongoDate::from(Timeless\now()));
        return $this;
    }

    public function execute()
    {
        if ($this->mustBeScheduled) {
            $this->job->save();
        } else {
            $this->job->execute();
        }
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
