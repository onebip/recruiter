<?php

namespace Recruiter;

use Recruiter\Job\Event;
use Recruiter\Job\EventListener;

class JobSendEventsToWorkableTest extends \PHPUnit_Framework_TestCase
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
        $listener = $this->getMock('StdClass', ['onEvent']);
        $listener->expects($this->once())->method('onEvent');
        $workable = new WorkableThatIsAlsoAnEventListener($listener);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface'));
    }
}

class WorkableThatIsAlsoAnEventListener implements Workable, EventListener
{
    use WorkableBehaviour;

    public function __construct($listener)
    {
        $this->listener = $listener;
    }

    public function onEvent(Event $e)
    {
        return $this->listener->onEvent($e);
    }

    public function execute()
    {
        throw new \Exception();
    }
}
