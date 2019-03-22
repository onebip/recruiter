<?php

namespace Recruiter;

interface Workable
{
    /**
     * Turn this `Recruiter\Workable` instance into a `Recruiter\Job` instance
     *
     * @param Recruiter $recruiter
     *
     * @return Job
     */
    public function asJobOf(Recruiter $recruiter);

    /**
     * Export parameters that need to be persisted
     *
     * @return array
     */
    public function export();

    /**
     * Import an array of parameters as a Workable instance
     *
     * @param array $parameters Previously exported parameters
     *
     * @return Workable
     */
    public static function import($parameters);
}
