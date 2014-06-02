<?php

namespace Recruiter\RetryPolicy;

use Recruiter\Retriable;
use Recruiter\RetryPolicy;
use Recruiter\JobAfterFailure;

abstract class BaseRetryPolicy implements RetryPolicy
{
    use Retriable;

    public function schedule(JobAfterFailure $job)
    {
        throw new \Exception('RetryPolicy::schedule(JobAfterFailure) need to be implemented');
    }
}
