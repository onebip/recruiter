<?php

namespace Recruiter;

use Timeless\Interval;
use Timeless as T;

class CleanerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->clock = T\clock()->stop();

        $this->jobRepository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->mongoLock = $this
            ->getMockBuilder('Onebip\Concurrency\Lock')
            ->disableOriginalConstructor()
            ->getMock();

        $this->cleaner = new Cleaner(
            $this->jobRepository,
            $this->mongoLock
        );

        $this->interval = new Interval('10');
    }

    public function tearDown()
    {
        T\clock()->start();
    }

    public function testShouldCreateCleaner()
    {
        $this->assertInstanceOf('Recruiter\Cleaner', $this->cleaner);
    }

    public function testShouldInvokeCallableIfLockIsAlreadyAquired()
    {
        $sentinel = false;
        $otherwise = function ($message) use (&$sentinel) {
            $sentinel = true;
        };
        $lockException = new \Onebip\Concurrency\LockNotAvailableException();
        $this
            ->mongoLock
            ->method('acquire')
            ->will($this->throwException($lockException));

        $this->cleaner->ensureIsTheOnlyOne($this->interval, $otherwise);

        $this->assertTrue($sentinel);
    }

    public function testShouldNotInvokeCallableIfLockIsNotAlreadyAcquired()
    {
        $sentinel = false;
        $otherwise = function ($message) use (&$sentinel) {
            $sentinel = true;
        };

        $this->cleaner->ensureIsTheOnlyOne($this->interval, $otherwise);

        $this->assertFalse($sentinel);
    }

    public function testDelegatesTheCleanupOfArchivedJobsToTheJobsRepository()
    {
        $now = $this->clock->now();
        $expectedUpperLimit = $now->before($this->interval);

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

    public function testShouldReleaseTheLock()
    {
        $this->mongoLock
            ->expects($this->once())
            ->method('release');

        $this->cleaner->bye();
    }
}
