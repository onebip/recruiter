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

        $this->dispatcher = $this->getMock(
            'Symfony\Component\EventDispatcher\EventDispatcherInterface');
    }

    public function testTakeRetryPolicyFromRetriableInstance()
    {
        $listener = $this->getMock('StdClass', ['onEvent']);
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
