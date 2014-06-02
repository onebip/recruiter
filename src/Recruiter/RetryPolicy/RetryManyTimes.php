<?php

namespace Recruiter\RetryPolicy;

use MongoDate;

use Recruiter\RetryPolicy;
use Recruiter\JobAfterFailure;

use Timeless;

class RetryManyTimes implements RetryPolicy
{
    private $retryHowManyTimes;
    private $secondsToWaitBeforeRetry;

    public function __construct($retryHowManyTimes, $secondsToWaitBeforeRetry)
    {
        $this->retryHowManyTimes = $retryHowManyTimes;
        $this->secondsToWaitBeforeRetry = $secondsToWaitBeforeRetry;
    }

    public function schedule(JobAfterFailure $job)
    {
        if ($job->numberOfAttempts() <= $this->retryHowManyTimes) {
            $job->scheduleIn(Timeless\seconds($this->secondsToWaitBeforeRetry));
        } else {
            $job->archive('tried-to-many-times');
        }
    }

    public function export()
    {
        return [
            'retry_how_many_times' => $this->retryHowManyTimes,
            'seconds_to_wait_before_retry' => $this->secondsToWaitBeforeRetry
        ];
    }

    public static function import($parameters)
    {
        return new self(
            $parameters['retry_how_many_times'],
            $parameters['seconds_to_wait_before_retry']
        );
    }
}
