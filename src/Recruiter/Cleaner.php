<?php

namespace Recruiter;

use Recruiter\Job\Repository;
use Onebip\Concurrency\MongoLock;
use Timeless\Interval;
use Onebip\Concurrency\LockNotAvailableException;

class Cleaner 
{
    const WAIT_FACTOR = 6;
    const POLL_TIME = 5;

    public function __construct(
        Repository $jobRepository,
        MongoLock $mongoLock
    )
    {
        $this->jobRepository = $jobRepository;
        $this->mongoLock = $mongoLock;
    }

    public function ensureIsTheOnlyOne(Interval $timeToWaitAtMost, $otherwise)
    {
        try {
            $this->lock->wait(self::POLL_TIME, $timeToWaitAtMost->seconds() * self::WAIT_FACTOR);
            $this->lock->acquire($this->leaseTimeOfLock($timeToWaitAtMost));
        } catch(LockNotAvailableException $e) {
            $otherwise($e->getMessage());
        }
    }

    /**
     * @return integer  seconds
     */
    private function leaseTimeOfLock(Interval $maximumBackoff)
    {
        return round($maximumBackoff->seconds() * self::LOCK_FACTOR);
    }
}
