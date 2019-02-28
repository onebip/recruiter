<?php

namespace Recruiter;

use PHPUnit\Framework\TestCase;
use Timeless\Interval;
use Timeless as T;

class CleanerTest extends TestCase
{
    public function setUp(): void
    {
        $this->clock = T\clock()->stop();
        $this->now = $this->clock->now();

        $this->jobRepository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->mongoLock = $this->createMock('Onebip\Concurrency\Lock');

        $this->cleaner = new Cleaner(
            $this->jobRepository,
            $this->mongoLock
        );

        $this->interval = Interval::parse('10s');
    }

    public function tearDown(): void
    {
        T\clock()->start();
    }

    public function testShouldCreateCleaner()
    {
        $this->assertInstanceOf('Recruiter\Cleaner', $this->cleaner);
    }

    public function testShouldNotBeTheMasterIfBothRefreshAndAcquireFail()
    {
        $lockException = new \Onebip\Concurrency\LockNotAvailableException();
        $this
            ->mongoLock
            ->method('refresh')
            ->will($this->throwException($lockException));

        $this
            ->mongoLock
            ->method('acquire')
            ->will($this->throwException($lockException));

        $result = $this->cleaner->becomeMaster($this->interval);

        $this->assertFalse($result);
    }

    public function testShouldBeTheMasterIfRefreshIsOk()
    {
        $lockException = new \Onebip\Concurrency\LockNotAvailableException();
        $this
            ->mongoLock
            ->method('acquire')
            ->will($this->throwException($lockException));

        $result = $this->cleaner->becomeMaster($this->interval);

        $this->assertTrue($result);
    }

    public function testShouldBeTheMasterIfRefreshFailsButAcquireIsOk()
    {
        $lockException = new \Onebip\Concurrency\LockNotAvailableException();
        $this
            ->mongoLock
            ->method('refresh')
            ->will($this->throwException($lockException));

        $result = $this->cleaner->becomeMaster($this->interval);

        $this->assertTrue($result);
    }

    public function testDelegatesTheCleanupOfArchivedJobsToTheJobsRepository()
    {
        $expectedUpperLimit = $this->now->before($this->interval);

        $this->jobRepository
            ->expects($this->once())
            ->method('cleanArchived')
            ->with($expectedUpperLimit)
            ->will($this->returnValue($jobsCleaned = 10));

        $this->assertEquals(
            $jobsCleaned,
            $this->cleaner->cleanArchived($this->interval)
        );
    }

    public function testShouldRefreshTheLock()
    {
        $expectedLockExpiration = round($this->interval->seconds() * Cleaner::LOCK_FACTOR);
        $this->mongoLock
            ->expects($this->once())
            ->method('refresh')
            ->with($expectedLockExpiration);

        $this->cleaner->becomeMaster($this->interval);
    }

    public function testShouldReleaseTheLock()
    {
        $this->mongoLock
            ->expects($this->once())
            ->method('release')
            ->with(false);

        $this->cleaner->bye();
    }
}
