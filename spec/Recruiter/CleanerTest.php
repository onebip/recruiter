<?php

namespace Recruiter;

use Timeless\Interval;

class CleanerTest extends \PHPUnit_Framework_TestCase
{
    // TODO: these tests are not making any time assumptions, please check
    protected function setUp()
    {
        $this->jobRepository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->mongoLock = $this
            ->getMockBuilder('Onebip\Concurrency\MongoLock')
            ->disableOriginalConstructor()
            ->getMock();

        $this->cleaner = new Cleaner(
            $this->jobRepository,
            $this->mongoLock
        );

        $this->interval = new Interval('10');
    }

    public function testShouldCreateCleaner()
    {
        $this->assertNotNull($this->cleaner);
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
        $this->jobRepository
            ->expects($this->once())
            ->method('cleanArchived')
            ->will($this->returnValue($jobsCleaned = 10));

        $this->assertEquals(
            $jobsCleaned,
            $this->cleaner->cleanArchived($this->interval)
        );
    }
}
