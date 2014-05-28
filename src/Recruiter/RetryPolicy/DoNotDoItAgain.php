<?php

namespace Recruiter\RetryPolicy;

use Recruiter\Job;
use Recruiter\RetryPolicy;

class DoNotDoItAgain implements RetryPolicy
{
    public function schedule(Job $job)
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
