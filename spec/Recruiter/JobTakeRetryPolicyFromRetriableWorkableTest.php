<?php

namespace Recruiter;

use PHPUnit\Framework\TestCase;
use Timeless as T;
use Recruiter\RetryPolicy;

class JobTakeRetryPolicyFromRetriableWorkableTest extends TestCase
{
    public function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventDispatcher = $this
            ->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    }

    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $retryPolicy = $this->createMock('Recruiter\RetryPolicy\BaseRetryPolicy');
        $retryPolicy->expects($this->once())->method('schedule');

        $workable = new WorkableThatIsAlsoRetriable($retryPolicy);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->eventDispatcher);
    }
}

class WorkableThatIsAlsoRetriable implements Workable, Retriable
{
    use WorkableBehaviour;

    public function __construct(RetryPolicy $retryWithPolicy)
    {
        $this->retryWithPolicy = $retryWithPolicy;
    }

    public function retryWithPolicy(): RetryPolicy
    {
        return $this->retryWithPolicy;
    }

    public function execute()
    {
        throw new \Exception();
    }
}
