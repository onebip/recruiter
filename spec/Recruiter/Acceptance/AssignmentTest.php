<?php
namespace Recruiter\Acceptance;

use Recruiter\Workable\LazyBones;

class AssignmentTest extends BaseAcceptanceTest
{
    public function testAJobCanBeAssignedAndExecuted()
    {
        LazyBones::waitForMs(200, 100)
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        $worker = $this->recruiter->hire();
        $assignments = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        $this->assertTrue((bool) $worker->work());
    }
}
