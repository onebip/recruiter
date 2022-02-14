<?php

namespace Recruiter;

use Recruiter\Job\Event;
use Recruiter\Job\EventListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use stdClass;

class JobSendEventsToWorkableTest extends TestCase
{
    public function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $listener = $this->getMockBuilder(stdClass::class)
                         ->addMethods(['onEvent'])
                         ->getMock();
        $listener
            ->expects($this->exactly(3))
            ->method('onEvent')
            ->withConsecutive(
                [$this->equalTo('job.started'), $this->anything()],
                [$this->equalTo('job.ended'), $this->anything()],
                [$this->equalTo('job.failure.last'), $this->anything()]
            );
        $workable = new WorkableThatIsAlsoAnEventListener($listener);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->dispatcher);
    }
}

class WorkableThatIsAlsoAnEventListener implements Workable, EventListener
{
    use WorkableBehaviour;

    public function __construct($listener)
    {
        $this->listener = $listener;
    }

    public function onEvent($channel, Event $e)
    {
        return $this->listener->onEvent($channel, $e);
    }

    public function execute()
    {
        throw new \Exception();
    }
}
