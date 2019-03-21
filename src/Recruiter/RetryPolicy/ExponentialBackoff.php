<?php

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
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

    public static function forTimes($retryHowManyTimes, $timeToInitiallyWaitBeforeRetry = 60)
    {
        return new static($retryHowManyTimes, $timeToInitiallyWaitBeforeRetry);
    }

    public function atFirstWaiting($timeToInitiallyWaitBeforeRetry)
    {
        return new static($this->retryHowManyTimes, $timeToInitiallyWaitBeforeRetry);
    }

    /**
     * @params integer $interval  in seconds
     * @params integer $timeToWaitBeforeRetry  in seconds
     */
    public static function forAnInterval($interval, $timeToInitiallyWaitBeforeRetry)
    {
        if (!($timeToInitiallyWaitBeforeRetry instanceof Interval)) {
            $timeToInitiallyWaitBeforeRetry = T\seconds($timeToInitiallyWaitBeforeRetry);
        }
        $numberOfRetries = round(
            log($interval / $timeToInitiallyWaitBeforeRetry->seconds())
            / log(2)
        );
        return new static($numberOfRetries, $timeToInitiallyWaitBeforeRetry);
    }

    public function __construct($retryHowManyTimes, $timeToInitiallyWaitBeforeRetry)
    {
        if (!($timeToInitiallyWaitBeforeRetry instanceof Interval)) {
            $timeToInitiallyWaitBeforeRetry = T\seconds($timeToInitiallyWaitBeforeRetry);
        }
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

    public function export(): array
    {
        return [
            'retry_how_many_times' => $this->retryHowManyTimes,
            'seconds_to_initially_wait_before_retry' => $this->timeToInitiallyWaitBeforeRetry->seconds()
        ];
    }

    public static function import(array $parameters): RetryPolicy
    {
        return new self(
            $parameters['retry_how_many_times'],
            T\seconds($parameters['seconds_to_initially_wait_before_retry'])
        );
    }

    public function isLastRetry(Job $job): bool
    {
        return $job->numberOfAttempts() > $this->retryHowManyTimes;
    }
}
