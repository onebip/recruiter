<?php

namespace Recruiter;

use Timeless as T;
use Recruiter\RetryPolicy;

class JobTakeRetryPolicyFromRetriableWorkableTest extends \PHPUnit_Framework_TestCase
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
        $retryPolicy = $this->getMock('Recruiter\RetryPolicy\BaseRetryPolicy');
        $retryPolicy->expects($this->once())->method('schedule');

        $workable = new WorkableThatIsAlsoRetriable($retryPolicy);

        $job = Job::around($workable, $this->repository);
        $job->execute();
    }
}

class WorkableThatIsAlsoRetriable implements Workable, Retriable
{
    use WorkableBehaviour;

    public function __construct(RetryPolicy $retryWithPolicy)
    {
        $this->retryWithPolicy = $retryWithPolicy;
    }

    public function retryWithPolicy()
    {
        return $this->retryWithPolicy;
    }

    public function execute()
    {
        throw new \Exception();
    }
}
