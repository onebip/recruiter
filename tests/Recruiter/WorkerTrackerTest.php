<?php

namespace Recruiter;

use Recruiter\Worker;

class WorkerTrackerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->repository = $this->getMockBuilder('Recruiter\Worker\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->aWorkerPid = 42;
        $this->worker = $this->getMockBuilder('Recruiter\Worker')
            ->disableOriginalConstructor()
            ->getMock();
        $this->worker
            ->expects($this->any())
            ->method('pid')
            ->will($this->returnValue($this->aWorkerPid));
    }

    public function testOnCleanUpIsGoingToRetireTheWorkerAssociatedTo()
    {
        $this->repository
            ->expects($this->once())
            ->method('retireWorkerWithPid')
            ->with($this->equalTo($this->aWorkerPid));

        $process = new Worker\Tracker();
        $process->associateTo($this->worker);
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
        $process = new Worker\Tracker();
        $sameInstanceInSupervisorProcess = clone $process;
        $process->associateTo($this->worker);

        $this->repository
            ->expects($this->once())
            ->method('retireWorkerWithPid')
            ->with($this->equalTo($this->aWorkerPid));

        $sameInstanceInSupervisorProcess->cleanUp($this->repository);
    }

    /**
     * @expectedException Exception
     */
    public function testCannotCleanUpMoreThanOneTime()
    {
        $process = new Worker\Tracker();
        $process->associateTo($this->worker);
        $process->cleanUp($this->repository);
        $process->cleanUp($this->repository);
    }
}
