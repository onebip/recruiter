<?php

namespace Recruiter;

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
    }

    public function testShouldCreateCleaner()
    {
        $this->assertNotNull($this->cleaner);
    }
}
