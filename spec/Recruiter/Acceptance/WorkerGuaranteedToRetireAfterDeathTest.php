<?php

namespace Recruiter\Acceptance;

class WorkerGuaranteedToRetireAfterDeathTest extends BaseAcceptanceTest
{
    /**
     * @group acceptance
     */
    public function testRetireAfterAskedToStop()
    {
        $numberOfWorkersBefore = $this->numberOfWorkers();
        $this->startWorker(function($processAndPipes) use ($numberOfWorkersBefore) {
            $this->waitForNumberOfWorkersToBe($numberOfWorkersBefore + 1);
            $this->stopWorkerWithSignal($processAndPipes, SIGTERM, function($stdout, $stderr) use ($numberOfWorkersBefore) {
                $numberOfWorkersCurrently = $this->numberOfWorkers();
                $this->assertEquals(
                    $numberOfWorkersBefore,
                    $numberOfWorkersCurrently,
                    "The number of workers before was $numberOfWorkersBefore and now after starting and stopping 1 we have $numberOfWorkersCurrently"
                );
            });
        });
    }
}
