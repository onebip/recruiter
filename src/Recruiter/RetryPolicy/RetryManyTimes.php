<?php

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;
use Recruiter\JobAfterFailure;
use Recruiter\Retriable;

use Timeless;
use Timeless\Duration;

class RetryManyTimes implements RetryPolicy
{
    private $retryHowManyTimes;
    private $timeToWaitBeforeRetry;

    use Retriable;

    public function __construct($retryHowManyTimes, Duration $timeToWaitBeforeRetry)
    {
        $this->retryHowManyTimes = $retryHowManyTimes;
        $this->timeToWaitBeforeRetry = $timeToWaitBeforeRetry;
    }

    public function schedule(JobAfterFailure $job)
    {
        if ($job->numberOfAttempts() <= $this->retryHowManyTimes) {
            $job->scheduleIn($this->timeToWaitBeforeRetry);
        } else {
            $job->archive('tried-to-many-times');
        }
    }

    public function export()
    {
        return [
            'retry_how_many_times' => $this->retryHowManyTimes,
            'seconds_to_wait_before_retry' => $this->timeToWaitBeforeRetry->seconds()
        ];
    }

    public static function import($parameters)
    {
        return new self(
            $parameters['retry_how_many_times'],
            Timeless\seconds($parameters['seconds_to_wait_before_retry'])
        );
    }
}
