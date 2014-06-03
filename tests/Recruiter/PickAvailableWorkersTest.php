<?php

namespace Recruiter;

use MongoId;

class PickAvailableWorkersTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->repository = $this
            ->getMockBuilder('MongoCollection')
            ->disableOriginalConstructor()
            ->getMock();

        $this->workersPerUnit = 42;
    }

    public function testNoWorkersAreFound()
    {
        $this->withNoAvailableWorkers();

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit, function($worksOn, $workers) {
            $this->fail('Should not be called when no workers are found');
        });

        $this->assertEquals(0, $picked);
    }

    public function testFewWorkersWithNoSpecifiSkill()
    {
        $callbackHasBeenCalled = false;
        $this->withAvailableWorkers(['*' => 3]);

        $picked = Worker::pickAvailableWorkers(
            $this->repository, $this->workersPerUnit,
            function($worksOn, $workers) use (&$callbackHasBeenCalled)
        {
            $callbackHasBeenCalled = true;
            $this->assertEquals('*', $worksOn);
            $this->assertEquals(3, count($workers));
            return count($workers);
        });

        $this->assertTrue($callbackHasBeenCalled, 'Callback should be called with available workers');
        $this->assertEquals(3, $picked);
    }

    public function testFewWorkersWithSameSkill()
    {
        $callbackHasBeenCalled = false;
        $this->withAvailableWorkers(['send-emails' => 3]);

        $picked = Worker::pickAvailableWorkers(
            $this->repository, $this->workersPerUnit,
            function($worksOn, $workers) use (&$callbackHasBeenCalled)
        {
            $callbackHasBeenCalled = true;
            $this->assertEquals('send-emails', $worksOn);
            $this->assertEquals(3, count($workers));
            return count($workers);
        });

        $this->assertTrue($callbackHasBeenCalled, 'Callback should be called with available workers');
        $this->assertEquals(3, $picked);
    }

    public function testFewWorkersWithSomeDifferentSkills()
    {
        $this->withAvailableWorkers(['send-emails' => 3, 'count-transactions' => 3]);
        $allSkillsGiven = [];
        $totalWorkersGiven = 0;

        $picked = Worker::pickAvailableWorkers(
            $this->repository, $this->workersPerUnit,
            function($worksOn, $workers) use (&$allSkillsGiven, &$totalWorkersGiven)
        {
            $allSkillsGiven[] = $worksOn;
            $totalWorkersGiven += count($workers);
            return count($workers);
        });

        $this->assertArrayAreEquals(['send-emails', 'count-transactions'], $allSkillsGiven);
        $this->assertEquals(6, $totalWorkersGiven);
        $this->assertEquals(6, $picked);
    }

    public function testMoreWorkersThanAllowedPerUnit()
    {
        $this->withAvailableWorkers(['send-emails' => $this->workersPerUnit + 10]);
        $totalWorkersGiven = 0;

        $picked = Worker::pickAvailableWorkers(
            $this->repository, $this->workersPerUnit,
            function($worksOn, $workers) use (&$totalWorkersGiven)
        {
            $totalWorkersGiven += count($workers);
            return count($workers);
        });

        $this->assertEquals($this->workersPerUnit, $totalWorkersGiven);
        $this->assertEquals($this->workersPerUnit, $picked);
    }

    public function testPickedIsTheSumOfTheCallbackResults()
    {
        $numberOfUnits = 2;
        $forEachUnitReturn = 2;
        $this->withAvailableWorkers(['send-emails' => 3, 'count-transactions' => 3]);

        $picked = Worker::pickAvailableWorkers(
            $this->repository, $this->workersPerUnit,
            function($worksOn, $workers) use ($forEachUnitReturn)
        {
            return $forEachUnitReturn;
        });

        $this->assertEquals($forEachUnitReturn * $numberOfUnits, $picked);
    }


    private function withAvailableWorkers($workers)
    {
        $workersThatShouldBeFound = [];
        foreach ($workers as $skill => $quantity) {
            for ($counter = 0; $counter < $quantity; $counter++) {
                $workerId = new MongoId();
                $workersThatShouldBeFound[(string)$workerId] = [
                    '_id' => $workerId,
                    'work_on' => $skill
                ];
            }
        }

        $this->repository
            ->expects($this->any())
            ->method('find')
            ->will($this->returnValue($workersThatShouldBeFound));
    }

    private function withNoAvailableWorkers()
    {
        $this->repository
            ->expects($this->any())
            ->method('find')
            ->will($this->returnValue([]));
    }

    private function assertArrayAreEquals($expected, $given)
    {
        sort($expected);
        sort($given);
        $this->assertEquals($expected, $given);
    }
}
