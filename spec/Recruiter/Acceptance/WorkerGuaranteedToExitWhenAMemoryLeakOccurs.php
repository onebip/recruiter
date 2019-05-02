<?php

namespace Recruiter\Acceptance;

use Onebip\Concurrency\Timeout;
use Recruiter\Workable\ConsumingMemoryCommand;
use Timeless as T;

class WorkerGuaranteedToRetireAfterDeathTest extends BaseAcceptanceTest
{
    /**
     * @group acceptance
     * @dataProvider provideMemoryConsumptions
     */
    public function testWorkerKillItselfAfterAMemoryLeakButNotAfterABigMemoryConsumptionWithoutLeak($withMemoryLeak, $howManyItems, $memoryLimit, $expectedWorkerAlive)
    {
        (new ConsumingMemoryCommand([
            'withMemoryLeak' => $withMemoryLeak,
            'howManyItems' => $howManyItems,
        ]))
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        $this->startRecruiter();

        $numberOfWorkersBefore = $this->numberOfWorkers();
        $this->startWorker([
            'memory-limit' => $memoryLimit,
        ]);
        $this->waitForNumberOfWorkersToBe($numberOfWorkersBefore + 1, 5);

        Timeout::inSeconds(5, function () { })
            ->until(function () {
                $at = T\now();
                $statistics = $this->recruiter->statistics($tag = null, $at);
                return $statistics['jobs']['queued'] == 0;
            });

        $numberOfWorkersCurrently = $this->numberOfWorkers();

        if ($expectedWorkerAlive) {
            $numberOfExpectedWorkers = $numberOfWorkersBefore + 1;
        } else {
            $numberOfExpectedWorkers = $numberOfWorkersBefore;
        }

        $this->assertEquals(
            $numberOfExpectedWorkers,
            $numberOfWorkersCurrently,
            "The number of workers before was $numberOfWorkersBefore and now after starting 1 and execute a job we have $numberOfWorkersCurrently"
        );
    }

    public static function provideMemoryConsumptions()
    {
        return [
            //legend: [$withMemoryLeak, $howManyItems, $memoryLimit, $expectedWorkerAlive],
            [false, 2000000, '20MB', true],
            [true, 2000000, '20MB', false],
            [true, 2000000, '128MB', true],
        ];
    }
}
