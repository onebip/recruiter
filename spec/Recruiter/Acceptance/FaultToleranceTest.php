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
        list ($assignments, $totalNumber) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        $this->assertEquals(1, $totalNumber);
    }
}
