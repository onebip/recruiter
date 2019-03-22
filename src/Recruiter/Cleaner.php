<?php

namespace Recruiter;

use Recruiter\Job\Repository;
use Timeless\Interval;
use Timeless as T;

class Cleaner
{
    /**
     * @var Repository
     */
    private $repository;

    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
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
    }
}
