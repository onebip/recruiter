<?php

namespace Recruiter;

use Recruiter\Worker;

class WorkerTrackerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->aWorkerPid = 42;
        $this->worker = $this->getMockBuilder('Recruiter\Worker')
            ->disableOriginalConstructor()
            ->getMock();
        $this->worker
            ->expects($this->any())
            ->method('pid')
            ->will($this->returnValue($this->aWorkerPid));
    }

    public function testInAnotherProcessStillRememberTheWorkerAssociatedTo()
    {
        // We want to simulate the following condition: when a worker process
        // dies the supervisor needs to know the worker document associated to
        // the died process. Here we use clone to simulate the fork of the
        // memory between the worker process and the supervisor process
        $tracker = new Worker\Tracker();
        $trackerInAnotherProcess = clone $tracker;
        $tracker->associateTo($this->worker);

        $this->assertEquals(
            Worker\Process::withPid($this->aWorkerPid),
            $trackerInAnotherProcess->process()
        );
    }

    public function testGetWorkerProcessMultipleTimesInSameProcess()
    {
        $tracker = new Worker\Tracker();
        $tracker->associateTo($this->worker);

        $this->assertEquals($tracker->process(), $tracker->process());
    }

    /**
     * @expectedException Exception
     */
    public function testRaiseExceptionWhenGetWorkerProcessMultipleTimesInTwoProcesses()
    {
        $tracker = new Worker\Tracker();
        $trackerInAnotherProcess = clone $tracker;
        $tracker->associateTo($this->worker);

        $tracker->process();
        $trackerInAnotherProcess->process();
    }
}
