<?php

namespace Recruiter\RetryPolicy;

use Exception;
use Recruiter\RetryPolicy;
use Recruiter\RetryPolicyBehaviour;
use Recruiter\JobAfterFailure;

abstract class BaseRetryPolicy implements RetryPolicy
{
    use RetryPolicyBehaviour;

    public function schedule(JobAfterFailure $job)
    {
        throw new Exception('RetryPolicy::schedule(JobAfterFailure) need to be implemented');
    }
}
