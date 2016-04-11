<?php

namespace Recruiter;

use Recruiter\Job\Repository;
use Onebip\Concurrency\MongoLock;

class Cleaner 
{
    public function __construct(
        Repository $jobRepository,
        MongoLock $mongoLock
    )
    {
        $this->jobRepository = $jobRepository;
        $this->mongoLock = $mongoLock;
    }
}
