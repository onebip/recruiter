<?php

namespace Recruiter\RetryPolicy;

use Recruiter\Retriable;
use Recruiter\RetryPolicy;
use Recruiter\JobAfterFailure;

class DoNotDoItAgain implements RetryPolicy
{
    use Retriable;

    public function schedule(JobAfterFailure $job)
    {
        // doing nothing means to avoid to reschedule the job
    }
}
