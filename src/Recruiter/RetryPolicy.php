<?php

namespace Recruiter;

interface RetryPolicy
{
    /**
     * Decide whether or not to reschedule a job. If you want to reschedule the
     * job use the appropriate methods on job or do nothing to if you don't
     * want to execute the job again
     *
     * This method can
     * - schedule the job
     * - archive the job
     * - do nothing (and the job will be archived anyway)
     *
     * @param Recruiter\Job $job
     *
     * @return void
     */
    public function schedule(JobAfterFailure $job);

    /**
     * Export retry policy parameters
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


    /**
     * @return int maximum number of retries
     */
    public function maximumNumberOfRetries();
}
