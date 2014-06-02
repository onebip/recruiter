<?php

namespace Recruiter;

use Timeless;
use Recruiter\RetryPolicy;

class JobToScheduleTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->clock = Timeless\clock()->stop();
        $this->job = $this
            ->getMockBuilder('Recruiter\Job')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function tearDown()
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

    public function testRetryWithPolicy()
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

    public function testDoNotRetry()
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
            ->method('schedule');

        $this->job
            ->expects($this->once())
            ->method('execute');

        (new JobToSchedule($this->job))
            ->execute();
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
}
