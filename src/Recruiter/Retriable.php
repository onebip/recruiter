<?php

namespace Recruiter;

interface Retriable
{
    /**
     * Declare what instance of `Recruiter\RetryPolicy` should be used for a `Recruiter\Workable`
     *
     * @return RetryPolicy
     */
    public function retryWithPolicy(): RetryPolicy;
}
