<?php

namespace Recruiter;

use Recruiter\RetryPolicy;

trait Recruitable
{
    public function asJobOf(Recruiter $recruiter)
    {
        return $recruiter->jobOf($this);
    }

    public function retryWithPolicy()
    {
        return new RetryPolicy\DoNotItAgain();
    }
}
