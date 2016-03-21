<?php

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;
use Recruiter\JobAfterFailure;

use Timeless as T;
use Timeless\Interval;

class ExponentialBackoff implements RetryPolicy
{
    private $retryHowManyTimes;
    private $timeToInitiallyWaitBeforeRetry;

    use RetryPolicyBehaviour;

    public function __construct($retryHowManyTimes, Interval $timeToInitiallyWaitBeforeRetry)
    {
        $this->retryHowManyTimes = $retryHowManyTimes;
        $this->timeToInitiallyWaitBeforeRetry = $timeToInitiallyWaitBeforeRetry;
    }

    public function schedule(JobAfterFailure $job)
    {
        if ($job->numberOfAttempts() <= $this->retryHowManyTimes) {
            $retryInterval = T\seconds(pow(2, $job->numberOfAttempts() - 1) * $this->timeToInitiallyWaitBeforeRetry->seconds());
            $job->scheduleIn($retryInterval);
        } else {
            $job->archive('tried-too-many-times');
        }
    }

    public function export()
    {
        return [
            'retry_how_many_times' => $this->retryHowManyTimes,
            'seconds_to_initially_wait_before_retry' => $this->timeToInitiallyWaitBeforeRetry->seconds()
        ];
    }

    public static function import($parameters)
    {
        return new self(
            $parameters['retry_how_many_times'],
            T\seconds($parameters['seconds_to_wait_before_retry'])
        );
    }
}
