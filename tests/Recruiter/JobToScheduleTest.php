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
                $this->equalTo($this->clock->now()->to('MongoDate'))
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

    public function testShouldNotExecuteJobWhenScheduled()
    {
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
            ->expects($this->once())
            ->method('execute');

        (new JobToSchedule($this->job))
            ->execute();
    }
}
