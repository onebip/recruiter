<?php

namespace Recruiter;

use Timeless\Interval;

class CleanerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->jobRepository = $this->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->mongoLock = $this->getMockBuilder('Onebip\Concurrency\MongoLock')
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

        $this->mongoLock->method('wait');
        $this->mongoLock->method('acquire');
        $this->cleaner->ensureIsTheOnlyOne($this->interval, $otherwise);

        $this->assertTrue($sentinel);
    }
}
