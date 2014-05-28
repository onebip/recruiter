<?php

namespace Recruiter;

interface RetryPolicy
{
    /**
     * Decide whether or not to reschedule a job. If you want to reschedule the
     * job use the appropriate methods on job or do nothing to if you don't
     * want to execute the job again
     *
     * ```php
     * // reschedule the job now
     * $job->scheduleAt()
     * ```
     *
     * @param Recruiter\Job $job
     *
     * @return void
     */
    public function schedule(Job $job);

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
     * @return Recruiter\RetryPoicy
     */
    public static function import($parameters);
}
