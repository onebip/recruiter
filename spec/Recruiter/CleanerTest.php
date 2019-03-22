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

        $this->cleaner = new Cleaner($this->jobRepository);

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
}
