<?php

namespace Recruiter\RetryPolicy;

use Recruiter\RetryPolicy;
use Recruiter\JobAfterFailure;

class DoNotDoItAgain implements RetryPolicy
{
    public function schedule(JobAfterFailure $job)
    {
        // doing nothing means to avoid to reschedule the job
    }

    public function export()
    {
        return [];
    }

    public static function import($parameters)
    {
        return new self();
    }
}
