<?php

namespace Recruiter;

use Timeless as T;
use Recruiter\RetryPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Recruiter\RetryPolicy\BaseRetryPolicy;

class JobTakeRetryPolicyFromRetriableWorkableTest extends TestCase
{
    public function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->eventDispatcher = $this
            ->createMock(EventDispatcherInterface::class);
    }

    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $retryPolicy = $this->createMock(BaseRetryPolicy::class);
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

    public function retryWithPolicy()
    {
        return $this->retryWithPolicy;
    }

    public function execute()
    {
        throw new \Exception();
    }
}
