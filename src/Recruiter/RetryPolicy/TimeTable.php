<?php

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;
use Recruiter\JobAfterFailure;

use Timeless as T;

use Exception;

class TimeTable implements RetryPolicy
{
    private $timeTable;
    private $howManyRetries;

    use RetryPolicyBehaviour;

    public function __construct(array $timeTable)
    {
        if (is_null($timeTable)) {
            $timeTable = [
                '5 minutes ago' => '1 minute',
                '1 hour ago' => '5 minutes',
                '24 hours ago' => '1 hour',
            ];
        }
        $this->timeTable = $timeTable;
        $this->howManyRetries = self::estimateHowManyRetriesIn($timeTable);
    }

    public function schedule(JobAfterFailure $job)
    {
        foreach ($this->timeTable as $timeSpent => $rescheduleIn) {
            if ($this->hasBeenCreatedLessThan($job, $timeSpent)) {
                return $this->rescheduleIn($job, $rescheduleIn);
            }
        }
    }

    public function maximumNumberOfRetries()
    {
        return $this->howManyRetries;
    }

    public function export()
    {
        return ['time_table' => $this->timeTable];
    }

    public static function import($parameters)
    {
        return new self($parameters['time_table']);
    }

    private function hasBeenCreatedLessThan($job, $relativeTime)
    {
        return $job->createdAt()->isAfter(
            T\Moment::fromTimestamp(strtotime($relativeTime, T\now()->seconds()))
        );
    }

    private function rescheduleIn($job, $relativeTime)
    {
        $job->scheduleAt(
            T\Moment::fromTimestamp(strtotime($relativeTime, T\now()->seconds()))
        );
    }

    private static function estimateHowManyRetriesIn($timeTable)
    {
        $now = T\now()->seconds();
        $howManyRetries = 0;
        $timeWindowInSeconds = 0;
        foreach ($timeTable as $timeWindow => $rescheduleTime) {
            $timeWindowInSeconds = ($now - strtotime($timeWindow, $now)) - $timeWindowInSeconds;
            if ($timeWindowInSeconds <= 0) {
                throw new Exception(
                    "Time window `$timeWindow` is invalid, must be in the past"
                );
            }
            $rescheduleTimeInSeconds = (strtotime($rescheduleTime, $now) - $now);
            if ($rescheduleTimeInSeconds <= 0) {
                throw new Exception(
                    "Reschedule time `$rescheduleTime` is invalid, must be in the future"
                );
            }
            if ($rescheduleTimeInSeconds > $timeWindowInSeconds) {
                throw new Exception(
                    "Reschedule time `$rescheduleTime` is invalid, must be greater than the time window"
                );
            }
            $howManyRetries += floor($timeWindowInSeconds / $rescheduleTimeInSeconds);
        }
        return $howManyRetries;
    }
}
