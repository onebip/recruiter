<?php
namespace Recruiter;

use Recruiter\Workable\AlwaysFail;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{
    public function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testRetryStatisticsOnFirstExecution()
    {
        $job = Job::around(new AlwaysFail, $this->repository);
        $retryStatistics = $job->retryStatistics();
        $this->assertInternalType('array', $retryStatistics);
        $this->assertArrayHasKey('job_id', $retryStatistics);
        $this->assertInternalType('string', $retryStatistics['job_id']);
        $this->assertArrayHasKey('retry_number', $retryStatistics);
        $this->assertEquals(0, $retryStatistics['retry_number']);
        $this->assertArrayHasKey('last_execution', $retryStatistics);
        $this->assertNull($retryStatistics['last_execution']);
    }

    /**
     * @depends testRetryStatisticsOnFirstExecution
     */
    public function testRetryStatisticsOnSubsequentExecutions()
    {
        $job = Job::around(new AlwaysFail, $this->repository);
        // maybe make the argument optional
        $job->execute($this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
        $job = Job::import($job->export(), $this->repository);
        $retryStatistics = $job->retryStatistics();
        $this->assertEquals(1, $retryStatistics['retry_number']);
        $this->assertArrayHasKey('last_execution', $retryStatistics);
        $lastExecution = $retryStatistics['last_execution'];
        $this->assertInternalType('array', $lastExecution);
        $this->assertArrayHasKey('started_at', $lastExecution);
        $this->assertArrayHasKey('ended_at', $lastExecution);
        $this->assertArrayHasKey('class', $lastExecution);
        $this->assertArrayHasKey('message', $lastExecution);
        $this->assertArrayHasKey('trace', $lastExecution);
        $this->assertEquals("Sorry, I'm good for nothing", $lastExecution['message']);
        $this->assertRegexp("/.*AlwaysFail->execute.*/", $lastExecution['trace']);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testArrayAsGroupIsNotAllowed()
    {
        $job = Job::around(new AlwaysFail, $this->repository);
        $job->inGroup(['test']);
    }
}
