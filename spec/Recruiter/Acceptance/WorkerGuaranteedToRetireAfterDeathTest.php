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
        $processAndPipes = $this->startWorker();
        $this->waitForNumberOfWorkersToBe($numberOfWorkersBefore + 1);
        $this->stopProcessWithSignal($processAndPipes, SIGTERM);
        $numberOfWorkersCurrently = $this->numberOfWorkers();
        $this->assertEquals(
            $numberOfWorkersBefore,
            $numberOfWorkersCurrently,
            "The number of workers before was $numberOfWorkersBefore and now after starting and stopping 1 we have $numberOfWorkersCurrently"
        );
    }
}
