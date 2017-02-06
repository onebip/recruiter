<?php
namespace Recruiter\Acceptance;

use Recruiter\Workable\AlwaysFail;
use Recruiter\Workable\AlwaysSucceed;
use Recruiter\Option\MemoryLimit;
use Recruiter\RetryPolicy\RetryManyTimes;
use Symfony\Component\EventDispatcher\Event;

class HooksTest extends BaseAcceptanceTest
{
    public function setUp()
    {
        $this->memoryLimit = new MemoryLimit('memory-limit', '64MB');
        parent::setUp();
    }

    public function testAfterFailureWithoutRetryEventIsFired()
    {
        $this->events = [];
        $this->recruiter
            ->getEventDispatcher()
            ->addListener('job.failure.last',
                function(Event $event) {
                    $this->events[] = $event;
                }
            );

        $job = (new AlwaysFail())
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        $worker = $this->recruiter->hire($this->memoryLimit);
        $this->recruiter->assignJobsToWorkers();
        $worker->work();

        $this->assertEquals(1, count($this->events));
        $this->assertInstanceOf('Recruiter\Job\Event', $this->events[0]);
        $this->assertEquals('not-scheduled-by-retry-policy', $this->events[0]->export()['why']);
    }

    public function testAfterLastFailureEventIsFired()
    {
        $this->events = [];
        $this->recruiter
            ->getEventDispatcher()
            ->addListener('job.failure.last',
                function(Event $event) {
                    $this->events[] = $event;
                }
            );

        $job = (new AlwaysFail())
            ->asJobOf($this->recruiter)
            ->retryWithPolicy(RetryManyTimes::forTimes(1, 0))
            ->inBackground()
            ->execute();

        $runAJob = function ($howManyTimes, $worker) {
            for ($i = 0; $i < $howManyTimes; $i++) {
                $this->recruiter->assignJobsToWorkers();
                $worker->work();
            }
        };

        $worker = $this->recruiter->hire($this->memoryLimit);
        $runAJob(2, $worker);

        $this->assertEquals(1, count($this->events));
        $this->assertInstanceOf('Recruiter\Job\Event', $this->events[0]);
        $this->assertEquals('tried-too-many-times', $this->events[0]->export()['why']);
    }

    public function testJobStartedIsFired()
    {
        $this->events = [];
        $this->recruiter
            ->getEventDispatcher()
            ->addListener('job.started',
                function(Event $event) {
                    $this->events[] = $event;
                }
            );

        $job = (new AlwaysSucceed())
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        $worker = $this->recruiter->hire($this->memoryLimit);
        $this->recruiter->assignJobsToWorkers();
        $worker->work();

        $this->assertEquals(1, count($this->events));
        $this->assertInstanceOf('Recruiter\Job\Event', $this->events[0]);
    }

    public function testJobEndedIsFired()
    {
        $this->events = [];
        $this->recruiter
            ->getEventDispatcher()
            ->addListener('job.ended',
                function(Event $event) {
                    $this->events[] = $event;
                }
            );

        (new AlwaysSucceed())
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        (new AlwaysFail())
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        $worker = $this->recruiter->hire($this->memoryLimit);
        $this->recruiter->assignJobsToWorkers();
        $worker->work();
        $this->recruiter->assignJobsToWorkers();
        $worker->work();

        $this->assertEquals(2, count($this->events));
        $this->assertInstanceOf('Recruiter\Job\Event', $this->events[0]);
        $this->assertInstanceOf('Recruiter\Job\Event', $this->events[1]);
    }
}
