<?php
namespace Recruiter\Acceptance;

use Recruiter\Option\MemoryLimit;

class FaultToleranceTest extends BaseAcceptanceTest
{
    public function testRecruiterCrashAfterLockingJobsBeforeAssignmentAndIsRestarted()
    {
        $memoryLimit = new MemoryLimit('memory-limit', '64MB');
        $this->enqueueJob();
        $worker = $this->recruiter->hire($memoryLimit);

        $assignments = $this->recruiter->bookJobsForWorkers();

        $this->recruiter->rollbackLockedJobs();
        list ($assignments, $totalNumber) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        $this->assertEquals(1, $totalNumber);
    }
}
