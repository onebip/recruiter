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
     * Export parameters that need to be persisted
     *
     * @return array
     */
    public function export();

    /**
     * Import retry policy parameters
     *
     * @param array $parameters Previously exported parameters
     *
     * @return Recruiter\RetryPolicy
     */
    public static function import($parameters);
}
