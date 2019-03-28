<?php

namespace Recruiter\Acceptance;

use Recruiter\Workable\FactoryMethodCommand;
use Recruiter\Workable\ThrowsFatalError;

class WorkerGuaranteedToExitWithFailureCodeInCaseOfExceptionTest extends BaseAcceptanceTest
{
    /**
     * @group acceptance
     */
    public function testInCaseOfExceptionTheExitCodeOfWorkerProcessIsNotZero()
    {
        (new ThrowsFatalError())
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute()
        ;

        $worker = $this->startWorker();
        $workerProcess = $worker[0];
        $this->waitForNumberOfWorkersToBe(1);
        list ($assignments, $_) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        $this->waitForNumberOfWorkersToBe(0, $seconds = 10);

        $status = proc_get_status($workerProcess);
        $this->assertNotEquals(0, $status['exitcode']);
    }
}
