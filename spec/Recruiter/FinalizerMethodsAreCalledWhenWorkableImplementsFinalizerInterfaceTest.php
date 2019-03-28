<?php

namespace Recruiter;

use Exception;
use PHPUnit\Framework\TestCase;

class FinalizerMethodsAreCalledWhenWorkableImplementsFinalizerInterfaceTest extends TestCase
{
    public function setUp(): void
    {
        $this->repository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->dispatcher = $this->createMock(
            'Symfony\Component\EventDispatcher\EventDispatcherInterface'
        );
    }

    public function testFinalizableFailureMethodsAreCalledWhenJobFails()
    {
        $exception = new \Exception('job was failed');
        $listener = $this->createPartialMock('StdClass', ['methodWasCalled']);
        $listener
            ->expects($this->exactly(3))
            ->method('methodWasCalled')
            ->withConsecutive(
                [$this->equalTo('afterFailure'), $exception],
                [$this->equalTo('afterLastFailure'), $exception],
                [$this->equalTo('finalize'), $exception]
            );
        $workable = new FinalizableWorkable(function () use ($exception) {
            throw $exception;
        }, $listener);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->dispatcher);
    }

    public function testFinalizableSuccessfullMethodsAreCalledWhenJobIsDone()
    {
        $listener = $this->createPartialMock('StdClass', ['methodWasCalled']);
        $listener
            ->expects($this->exactly(2))
            ->method('methodWasCalled')
            ->withConsecutive(
                [$this->equalTo('afterSuccess')],
                [$this->equalTo('finalize')]
            );
        $workable = new FinalizableWorkable(function () {
            return true;
        }, $listener);

        $job = Job::around($workable, $this->repository);
        $job->execute($this->dispatcher);
    }
}

class FinalizableWorkable implements Workable, Finalizable
{
    use WorkableBehaviour;
    use FinalizableBehaviour;

    private $whatToDo;

    private $listener;

    public function __construct(callable $whatToDo, $listener)
    {
        $this->listener = $listener;
        $this->whatToDo = $whatToDo;
    }

    public function execute()
    {
        $whatToDo = $this->whatToDo;
        return $whatToDo();
    }

    public function afterSuccess()
    {
        $this->listener->methodWasCalled(__FUNCTION__);
    }

    public function afterFailure(Exception $e)
    {
        $this->listener->methodWasCalled(__FUNCTION__, $e);
    }

    public function afterLastFailure(Exception $e)
    {
        $this->listener->methodWasCalled(__FUNCTION__, $e);
    }

    public function finalize(?Exception $e = null)
    {
        $this->listener->methodWasCalled(__FUNCTION__, $e);
    }
}
