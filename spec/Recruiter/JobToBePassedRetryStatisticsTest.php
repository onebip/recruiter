<?php

namespace Recruiter;

use Timeless as T;
use Recruiter\RetryPolicy\DoNotDoItAgain;

class JobToBePassedRetryStatisticsTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->repository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $workable = new WorkableThatUsesRetryStatistics();

        $job = Job::around($workable, $this->repository);
        $job->execute($this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
        $this->assertTrue($job->done(), "Job requiring retry statistics was not executed correctly: " . var_export($job->export(), true));
    }
}

class WorkableThatUsesRetryStatistics implements Workable, Retriable
{
    use WorkableBehaviour;

    public function retryWithPolicy()
    {
        return new DoNotDoItAgain();
    }

    public function execute(array $retryStatistics)
    {
    }
}
