<?php
namespace Recruiter\Acceptance;


class FaultToleranceTest extends BaseAcceptanceTest
{
    public function testRecruiterCrashAfterLockingJobsBeforeAssignmentAndIsRestarted()
    {
        $this->enqueueJob();
        $worker = $this->recruiter->hire();

        $assignments = $this->recruiter->bookJobsForWorkers();

        $this->recruiter->rollbackLockedJobs();
        $assignments = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
    }
}
