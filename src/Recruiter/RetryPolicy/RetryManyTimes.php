<?php
namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;
use Recruiter\JobAfterFailure;

use Timeless as T;
use Timeless\Interval;

class RetryManyTimes implements RetryPolicy
{
    private $retryHowManyTimes;
    private $timeToWaitBeforeRetry;

    use RetryPolicyBehaviour;

    public function __construct($retryHowManyTimes, $timeToWaitBeforeRetry)
    {
        if (!($timeToWaitBeforeRetry instanceof Interval)) {
            $timeToWaitBeforeRetry = T\seconds($timeToWaitBeforeRetry);
        }
        $this->retryHowManyTimes = $retryHowManyTimes;
        $this->timeToWaitBeforeRetry = $timeToWaitBeforeRetry;
    }

    public static function forTimes($retryHowManyTimes, $timeToWaitBeforeRetry = 60)
    {
        return new static($retryHowManyTimes, $timeToWaitBeforeRetry);
    }

    public function schedule(JobAfterFailure $job)
    {
        if ($job->numberOfAttempts() <= $this->retryHowManyTimes) {
            $job->scheduleIn($this->timeToWaitBeforeRetry);
        } else {
            $job->archive('tried-too-many-times');
        }
    }

    public function export(): array
    {
        return [
            'retry_how_many_times' => $this->retryHowManyTimes,
            'seconds_to_wait_before_retry' => $this->timeToWaitBeforeRetry->seconds()
        ];
    }

    public static function import(array $parameters): RetryPolicy
    {
        return new self(
            $parameters['retry_how_many_times'],
            T\seconds($parameters['seconds_to_wait_before_retry'])
        );
    }

    public function maximumNumberOfRetries(): int
    {
        return $this->retryHowManyTimes;
    }
}
