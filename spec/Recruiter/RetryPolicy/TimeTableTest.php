<?php

namespace Recruiter\RetryPolicy;

use Exception;
use PHPUnit\Framework\TestCase;
use Timeless as T;

class TimeTableTest extends TestCase
{
    public function setUp(): void
    {
        $this->scheduler = new TimeTable([
            '5 minutes ago' => '1 minute',
            '1 hour ago' => '5 minutes',
            '24 hours ago' => '1 hour',
        ]);
    }

    public function testShouldRescheduleInOneMinuteWhenWasCreatedLessThanFiveMinutesAgo()
    {
        $expectedToBeScheduledAt = T\minute(1)->fromNow()->toSecondPrecision();
        $wasCreatedAt = T\seconds(10)->ago();
        $job = $this->givenJobThat($wasCreatedAt);
        $job->expects($this->once())
            ->method('scheduleAt')
            ->with($this->equalTo($expectedToBeScheduledAt));
        $this->scheduler->schedule($job);
    }

    public function testShouldRescheduleInFiveMinutesWhenWasCreatedLessThanOneHourAgo()
    {
        $expectedToBeScheduledAt = T\minutes(5)->fromNow()->toSecondPrecision();
        $wasCreatedAt = T\minutes(30)->ago();
        $job = $this->givenJobThat($wasCreatedAt);
        $job->expects($this->once())
            ->method('scheduleAt')
            ->with($this->equalTo($expectedToBeScheduledAt));
        $this->scheduler->schedule($job);
    }

    public function testShouldRescheduleInFiveMinutesWhenWasCreatedLessThan24HoursAgo()
    {
        $expectedToBeScheduledAt = T\hour(1)->fromNow()->toSecondPrecision();
        $wasCreatedAt = T\hours(3)->ago();
        $job = $this->givenJobThat($wasCreatedAt);
        $job->expects($this->once())
            ->method('scheduleAt')
            ->with($this->equalTo($expectedToBeScheduledAt));
        $this->scheduler->schedule($job);
    }

    public function testShouldNotBeRescheduledWhenWasCreatedMoreThan24HoursAgo()
    {
        $job = $this->jobThatWasCreated('2 days ago');
        $job->expects($this->never())->method('scheduleAt');
        $this->scheduler->schedule($job);
    }

    public function testCanCalculateTheMaximumNumberOfRetries()
    {
        $tt = new TimeTable(['1 minute ago' => '1 second']);
        $this->assertEquals(60, $tt->maximumNumberOfRetries());

        $tt = new TimeTable(['1 minute ago' => '1 second', '5 minutes ago' => '1 minute']);
        $this->assertEquals(64, $tt->maximumNumberOfRetries());

        $tt = new TimeTable(['3 minute ago' => '1 second', '5 minutes ago' => '1 minute']);
        $this->assertEquals(182, $tt->maximumNumberOfRetries());
    }

    public function testInvalidTimeTableBecauseTimeWindow()
    {
        $this->expectException(Exception::class);
        $tt = new TimeTable(['1 minute' => '1 second']);
    }

    public function testInvalidTimeTableBecauseRescheduleTime()
    {
        $this->expectException(Exception::class);
        $tt = new TimeTable(['1 minute ago' => '1 second ago']);
    }

    public function testInvalidTimeTableBecauseRescheduleTimeIsGreaterThanTimeWindow()
    {
        $this->expectException(Exception::class);
        $tt = new TimeTable(['1 minute ago' => '2 minutes']);
    }

    private function givenJobThat(T\Moment $wasCreatedAt)
    {
        $job = $this->getMockBuilder('Recruiter\JobAfterFailure')
            ->disableOriginalConstructor()
            ->setMethods(['createdAt', 'scheduleAt'])
            ->getMock();
        $job->expects($this->any())
            ->method('createdAt')
            ->will($this->returnValue($wasCreatedAt));
        return $job;
    }

    private function jobThatWasCreated($relativeTime)
    {
        $wasCreatedAt = T\Moment::fromTimestamp(strtotime($relativeTime), T\now()->seconds());
        $job = $this->getMockBuilder('Recruiter\JobAfterFailure')
            ->disableOriginalConstructor()
            ->setMethods(['createdAt', 'scheduleAt'])
            ->getMock();
        $job->expects($this->any())
            ->method('createdAt')
            ->will($this->returnValue($wasCreatedAt));
        return $job;
    }
}
