<?php

namespace Recruiter;

use PHPUnit\Framework\TestCase;
use Timeless as T;
use Recruiter\RetryPolicy;

class JobToScheduleTest extends TestCase
{
    public function setUp(): void
    {
        $this->clock = T\clock()->stop();
        $this->job = $this
            ->getMockBuilder('Recruiter\Job')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function tearDown(): void
    {
        $this->clock->start();
    }

    public function testInBackgroundShouldScheduleJobNow()
    {
        $this->job
            ->expects($this->once())
            ->method('scheduleAt')
            ->with(
                $this->equalTo($this->clock->now())
            );

        (new JobToSchedule($this->job))
            ->inBackground()
            ->execute();
    }

    public function testScheduledInShouldScheduleInCertainAmountOfTime()
    {
        $amountOfTime = T\minutes(10);
        $this->job
            ->expects($this->once())
            ->method('scheduleAt')
            ->with(
                $this->equalTo($amountOfTime->fromNow())
            );

        (new JobToSchedule($this->job))
            ->scheduleIn($amountOfTime)
            ->execute();
    }

    public function testConfigureRetryPolicy()
    {
        $doNotDoItAgain = new RetryPolicy\DoNotDoItAgain();

        $this->job
            ->expects($this->once())
            ->method('retryWithPolicy')
            ->with($doNotDoItAgain);

        (new JobToSchedule($this->job))
            ->inBackground()
            ->retryWithPolicy($doNotDoItAgain)
            ->execute();
    }

    public function tesShortcutToConfigureJobToNotBeRetried()
    {
        $this->job
            ->expects($this->once())
            ->method('retryWithPolicy')
            ->with($this->isInstanceOf('Recruiter\RetryPolicy\DoNotDoItAgain'));

        (new JobToSchedule($this->job))
            ->inBackground()
            ->doNotRetry()
            ->execute();
    }

    public function testShouldNotExecuteJobWhenScheduled()
    {
        $this->job
            ->expects($this->once())
            ->method('save');

        $this->job
            ->expects($this->never())
            ->method('execute');

        (new JobToSchedule($this->job))
            ->inBackground()
            ->execute();
    }

    public function testShouldExecuteJobWhenNotScheduled()
    {
        $this->job
            ->expects($this->never())
            ->method('scheduleAt');

        $this->job
            ->expects($this->once())
            ->method('execute');

        (new JobToSchedule($this->job))
            ->execute(
                $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface')
            );
    }

    public function testConfigureMethodToCallOnWorkableInJob()
    {
        $this->job
            ->expects($this->once())
            ->method('methodToCallOnWorkable')
            ->with('send');

        (new JobToSchedule($this->job))
            ->send();
    }

    public function testReturnsJobId()
    {
        $this->job
            ->expects($this->any())
            ->method('id')
            ->will($this->returnValue('42'));

        $this->assertEquals(
            '42',
            (new JobToSchedule($this->job))
                ->execute(
                    $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface')
                )
        );
    }
}
