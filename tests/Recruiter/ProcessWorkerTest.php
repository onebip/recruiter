<?php

namespace Recruiter;

class ProcessWorkerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->repository = $this->getMockBuilder('Recruiter\Worker\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->aWorkerId = 42;
        $this->worker = $this->getMockBuilder('Recruiter\Worker')
            ->disableOriginalConstructor()
            ->getMock();
        $this->worker
            ->expects($this->any())
            ->method('id')
            ->will($this->returnValue($this->aWorkerId));
    }

    public function testOnCleanUpIsGoingToRetireTheWorkerAssociatedTo()
    {
        $this->repository
            ->expects($this->once())
            ->method('retire')
            ->with($this->equalTo($this->aWorkerId));

        $process = new ProcessWorker();
        $process->associatedTo($this->worker);
        $process->cleanUp($this->repository);
    }

    public function testInAnotherProcessStillRememberTheWorkerAssociatedTo()
    {
        // We want to simulate the following condition: when a worker process
        // dies the supervisor needs to know the worker associated to the died
        // process. Problem is that the worker is associated to the worker in
        // the worker process and needs  to be retrieved in the supervisor
        // process. Here we use clone to simulate the fork of the memory
        // between the worker process and the supervisor process
        $process = new ProcessWorker();
        $sameInstanceInSupervisorProcess = clone $process;
        $process->associatedTo($this->worker);

        $this->repository
            ->expects($this->once())
            ->method('retire')
            ->with($this->equalTo($this->aWorkerId));

        $sameInstanceInSupervisorProcess->cleanUp($this->repository);
    }

    /**
     * @expectedException Exception
     */
    public function testCannotCleanUpMoreThanOneTime()
    {
        $process = new ProcessWorker();
        $process->associatedTo($this->worker);
        $process->cleanUp($this->repository);
        $process->cleanUp($this->repository);
    }
}
