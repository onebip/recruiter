<?php
namespace Recruiter\Acceptance;

use Onebip\Clock\SystemClock;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Workable\LazyBones;
use Recruiter\Workable\ThrowsFatalError;
use Recruiter\Workable\FailsInConstructor;
use Recruiter\RetryPolicy\RetryManyTimes;
use Timeless as T;

class FaultToleranceTest extends BaseAcceptanceTest
{
    public function testRecruiterCrashAfterLockingJobsBeforeAssignmentAndIsRestarted()
    {
        $memoryLimit = new MemoryLimit('64MB');
        $this->enqueueJob();
        $worker = $this->recruiter->hire($memoryLimit);
        $this->recruiter->bookJobsForWorkers();
        $this->recruiter->rollbackLockedJobs();
        list ($assignments, $totalNumber) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        $this->assertEquals(1, $totalNumber);
    }

    public function testRetryPolicyMustBeAppliedEvenWhenWorkerDiesInConstructor()
    {
        (new FailsInConstructor([], false))
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->retryWithPolicy(RetryManyTimes::forTimes(1, 0))
            ->execute();

        $worker = $this->startWorker();
        $this->waitForNumberOfWorkersToBe(1);

        list ($assignments, $_) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        sleep(2);
        $jobDocument = current($this->scheduled->find()->toArray());
        $this->assertEquals(1, $jobDocument['attempts']);
        $this->assertEquals('Recruiter\\Workable\\FailsInConstructor', $jobDocument['workable']['class']);
        $this->assertStringContainsString('This job failed while instantiating a workable', $jobDocument['last_execution']['message']);
        $this->assertStringContainsString('I am supposed to fail in constructor code for testing purpose', $jobDocument['last_execution']['message']);

        list ($assignments, $_) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        sleep(2);
        $jobDocument = current($this->archived->find()->toArray());
        $this->assertEquals(2, $jobDocument['attempts']);
        $this->assertEquals('Recruiter\\Workable\\FailsInConstructor', $jobDocument['workable']['class']);
        $this->assertStringContainsString('This job failed while instantiating a workable', $jobDocument['last_execution']['message']);
        $this->assertStringContainsString('I am supposed to fail in constructor code for testing purpose', $jobDocument['last_execution']['message']);

        list ($assignments, $_) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(0, count($assignments));
    }

    public function testRetryPolicyMustBeAppliedEvenWhenWorkerDies()
    {
        // This job will fail with a fatal error and we want it to be
        // retried at most 1 time, this means at most 2
        // executions. The problem is that the retry policy is
        // evaluated after the execution but fatal errors are not
        // catchable and so the job will stay scheduled forever
        (new ThrowsFatalError())
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->retryWithPolicy(RetryManyTimes::forTimes(1, 0))
            ->execute();

        // Right now we recover for dead jobs when we
        // Recruiter::retireDeadWorkers and when we
        // Recruiter::assignJobsToWorkers marking them as `crashed`

        // The retry policy is applied to crashed jobs before the
        // execution

        // First execution of the job
        $worker = $this->startWorker();
        $this->waitForNumberOfWorkersToBe(1);
        list ($assignments, $_) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        sleep(2);
        // The worker is dead and the job is not properly scheduled
        $this->recruiter->retireDeadWorkers(new SystemClock(), T\seconds(0));
        $this->waitForNumberOfWorkersToBe(0);

        // Second execution of the job
        $worker = $this->startWorker();
        $this->waitForNumberOfWorkersToBe(1);
        // Here the job is assigned and rescheduled by the retry policy because found crashed
        list ($assignments, $_) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        sleep(2);
        // The worker is dead and the job is not properly scheduled
        $this->recruiter->retireDeadWorkers(new SystemClock(), T\seconds(0));
        $this->waitForNumberOfWorkersToBe(0);
        $this->assertJobIsMarkedAsCrashed();

        // Third execution of the job
        $worker = $this->startWorker();
        $this->waitForNumberOfWorkersToBe(1);
        // Here the job is assigned and archived by the retry policy
        // because found crashed and because it has been already
        // executed 2 times
        list ($assignments, $_) = $this->recruiter->assignJobsToWorkers();
        $this->assertEquals(1, count($assignments));
        sleep(1);
        // The worker is not dead and the job is not scheduled anymore
        $this->assertEquals(0, $this->recruiter->queued());
    }

    private function assertJobIsMarkedAsCrashed()
    {
        $jobs = iterator_to_array($this->recruiterDb->selectCollection('scheduled')->find());
        $this->assertEquals(1, count($jobs));
        foreach ($jobs as $job) {
            $this->assertArrayHasKey('last_execution', $job);
            $this->assertArrayHasKey('crashed', $job['last_execution']);
            $this->assertArrayHasKey('scheduled_at', $job['last_execution']);
            $this->assertArrayHasKey('started_at', $job['last_execution']);
            $this->assertArrayNotHasKey('ended_at', $job['last_execution']);
            $this->assertTrue($job['last_execution']['crashed']);
        }
    }
}
