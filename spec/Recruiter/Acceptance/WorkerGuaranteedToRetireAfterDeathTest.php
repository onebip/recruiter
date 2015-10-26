<?php

namespace Recruiter;

class WorkerGuaranteedToRetireAfterDeathTest extends BaseAcceptanceTest
{
    /**
     * @group acceptance
     */
    public function testRetireAfterAskedToStop()
    {
        $this->markTestSkipped();
        $numberOfWorkersBefore = $this->numberOfWorkers();
        $this->startWorker(function($process) use ($numberOfWorkersBefore) {
            $this->assertEquals($numberOfWorkersBefore + 1, $this->numberOfWorkers());
            $this->stopWorkerWithSignal($process, SIGTERM, function($stdout, $stderr) use ($numberOfWorkersBefore) {
                $this->assertEquals($numberOfWorkersBefore, $this->numberOfWorkers());
            });
        });
    }
}
