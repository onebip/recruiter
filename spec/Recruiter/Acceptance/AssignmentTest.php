<?php
namespace Recruiter\Acceptance;

use Recruiter\Workable\LazyBones;
use Recruiter\Option\MemoryLimit;

class AssignmentTest extends BaseAcceptanceTest
{
    public function testAJobCanBeAssignedAndExecuted()
    {
        $memoryLimit = new MemoryLimit('memory-limit', '64MB');
        LazyBones::waitForMs(200, 100)
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        $worker = $this->recruiter->hire($memoryLimit);
        list ($assignments, $totalNumber) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        $this->assertEquals(1, $totalNumber);
        $this->assertTrue((bool) $worker->work());
    }
}
