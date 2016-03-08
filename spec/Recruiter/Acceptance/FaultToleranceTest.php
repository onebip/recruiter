<?php
namespace Recruiter\Acceptance;


class FaultToleranceTest extends BaseAcceptanceTest
{
    public function testRecruiterCrashAfterLockingJobsBeforeAssignmentAndIsRestarted()
    {
        $this->enqueueJob();
        $worker = $this->recruiter->hire();

        $assignments = $this->recruiter->assignJobsToWorkers1();
        $assignments = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, $assignments);
    }
}
