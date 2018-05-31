<?php

namespace Recruiter;

use Recruiter\Job\Repository;
use Onebip\Concurrency\Lock;
use Timeless\Interval;
use Timeless as T;
use Onebip\Concurrency\LockNotAvailableException;

class Cleaner
{
    const WAIT_FACTOR = 6;
    const POLL_TIME = 5;
    const LOCK_FACTOR = 3;

    public function __construct(Repository $repository, Lock $lock)
    {
        $this->repository = $repository;
        $this->lock = $lock;
    }

    /**
     * @step
     * @return bool it worked
     */
    public function becomeMaster(Interval $timeToWaitAtMost): bool
    {
        try {
            $this->lock->refresh($this->leaseTimeOfLock($timeToWaitAtMost));
        } catch(LockNotAvailableException $e) {
            try {
                $this->lock->wait(self::POLL_TIME, $timeToWaitAtMost->seconds() * self::WAIT_FACTOR);
                $this->lock->acquire($this->leaseTimeOfLock($timeToWaitAtMost));
            } catch(LockNotAvailableException $e) {
                return false;
            }
        }

        return true;
    }

    public function cleanArchived(Interval $gracePeriod)
    {
        $upperLimit = T\now()->before($gracePeriod);
        return $this->repository->cleanArchived($upperLimit);
    }

    public function cleanScheduled(Interval $gracePeriod = null)
    {
        $upperLimit = T\now();
        if (!is_null($gracePeriod)) {
            $upperLimit = $upperLimit->before($gracePeriod);
        }
        return $this->repository->cleanScheduled($upperLimit);
    }

    public function bye()
    {
        $this->lock->release(false);
    }

    /**
     * @return integer  seconds
     */
    private function leaseTimeOfLock(Interval $maximumBackoff)
    {
        return round($maximumBackoff->seconds() * self::LOCK_FACTOR);
    }
}
