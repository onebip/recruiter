<?php
namespace Recruiter\RetryPolicy;

use Timeless as T;

class ExponentialBackoffTest extends \PHPUnit_Framework_TestCase
{
    public function testOnTheFirstFailureUsesTheSpecifiedInterval()
    {
        $job = $this->jobExecutedFor(1);
        $retryPolicy = new ExponentialBackoff(100, T\seconds(5));

        $job->expects($this->once())
            ->method('scheduleIn')
            ->with(T\seconds(5));
        $retryPolicy->schedule($job);
    }

    public function testAfterEachFailureDoublesTheAmountOfTimeToWaitBetweenRetries()
    {
        $job = $this->jobExecutedFor(2);
        $retryPolicy = new ExponentialBackoff(100, T\seconds(5));

        $job->expects($this->once())
            ->method('scheduleIn')
            ->with(T\seconds(10));
        $retryPolicy->schedule($job);
    }

    public function testAfterTooManyFailuresGivesUp()
    {
        $job = $this->jobExecutedFor(101);
        $retryPolicy = new ExponentialBackoff(100, T\seconds(5));

        $job->expects($this->once())
            ->method('archive')
            ->with('tried-too-many-times');
        $retryPolicy->schedule($job);
    }

    public function testCanBeCreatedByTargetingAMaximumInterval()
    {
        $this->assertEquals(
            ExponentialBackoff::forAnInterval(1025, T\seconds(1)),
            new ExponentialBackoff(10, 1)
        );
    }

    private function jobExecutedFor($times)
    {
        $job = $this->getMockBuilder('Recruiter\JobAfterFailure')->disableOriginalConstructor()->getMock();
        $job->expects($this->any())
            ->method('numberOfAttempts')
            ->will($this->returnValue($times));
        return $job;
    }
}
