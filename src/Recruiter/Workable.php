<?php

namespace Recruiter;

interface Workable
{
    /**
     * Turn this `Recruiter\Workable` instance into a `Recruiter\Job` instance
     *
     * @param Recruiter\Recruiter $recruiter
     *
     * @return Recruiter\Job
     */
    public function asJobOf(Recruiter $recruiter);

    /**
     * Returns the retry policy for this `Recruiter\Workable` instance
     *
     * @return Recruiter\RetryPolicy
     */
    public function retryWithPolicy();

    /**
     * Export parameters that need to be persisted
     *
     * @return array
     */
    public function export();

    /**
     * Import retry policy parameters
     *
     * @param array $parameters Previously exported parameters
     * @param Recruiter\RetryPolicy $scheduler
     *
     * @return Recruiter\RetryPoicy
     */
    public static function import($parameters, RetryPolicy $scheduler = null);
}
