<?php

namespace Recruiter;

use Recruiter\Job\Repository;
use Onebip\Concurrency\MongoLock;
use Timeless\Interval;
use Timeless as T;
use Onebip\Concurrency\LockNotAvailableException;

class Cleaner
{
    const WAIT_FACTOR = 6;
    const POLL_TIME = 5;
    const LOCK_FACTOR = 3;

    public function __construct(Repository $repository, MongoLock $lock)
    {
        $this->repository = $repository;
        $this->lock = $lock;
    }

    public function ensureIsTheOnlyOne(Interval $timeToWaitAtMost, $otherwise)
    {
        try {
            $this->lock->wait(
                self::POLL_TIME,
                $timeToWaitAtMost->seconds() * self::WAIT_FACTOR
            );
            $this->lock->acquire($this->leaseTimeOfLock($timeToWaitAtMost));
        } catch(LockNotAvailableException $e) {
            $otherwise($e->getMessage());
        }
    }

    public function cleanArchived(Interval $gracePeriod)
    {
        $upperLimit = T\now()->before($gracePeriod);
        return $this->repository->cleanArchived($upperLimit);
    }

    public function bye()
    {
        $this->lock->release();
    }

    /**
     * @return integer  seconds
     */
    private function leaseTimeOfLock(Interval $maximumBackoff)
    {
        return round($maximumBackoff->seconds() * self::LOCK_FACTOR);
    }
}
