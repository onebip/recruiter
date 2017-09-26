<?php

namespace Recruiter;

use MongoId;
use ArrayIterator;

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

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        $this->assertEquals([], $picked);
    }

    public function testFewWorkersWithNoSpecifiSkill()
    {
        $callbackHasBeenCalled = false;
        $this->withAvailableWorkers(['*' => 3]);

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        list ($worksOn, $workers) = $picked[0];
        $this->assertEquals('*', $worksOn);
        $this->assertEquals(3, count($workers));
    }

    public function testFewWorkersWithSameSkill()
    {
        $callbackHasBeenCalled = false;
        $this->withAvailableWorkers(['send-emails' => 3]);

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        list ($worksOn, $workers) = $picked[0];
        $this->assertEquals('send-emails', $worksOn);
        $this->assertEquals(3, count($workers));
    }

    public function testFewWorkersWithSomeDifferentSkills()
    {
        $this->withAvailableWorkers(['send-emails' => 3, 'count-transactions' => 3]);
        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        $allSkillsGiven = [];
        $totalWorkersGiven = 0;
        foreach ($picked as $pickedRow) {
            list ($worksOn, $workers) = $pickedRow;
            $allSkillsGiven[] = $worksOn;
            $totalWorkersGiven += count($workers);
        }
        $this->assertArrayAreEquals(['send-emails', 'count-transactions'], $allSkillsGiven);
        $this->assertEquals(6, $totalWorkersGiven);
    }

    public function testMoreWorkersThanAllowedPerUnit()
    {
        $this->withAvailableWorkers(['send-emails' => $this->workersPerUnit + 10]);

        $picked = Worker::pickAvailableWorkers($this->repository, $this->workersPerUnit);

        $totalWorkersGiven = 0;
        foreach ($picked as $pickedRow) {
            list ($worksOn, $workers) = $pickedRow;
            $totalWorkersGiven += count($workers);
        }
        $this->assertEquals($this->workersPerUnit, $totalWorkersGiven);
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
            ->will($this->returnValue(new ArrayIterator($workersThatShouldBeFound)));
    }

    private function withNoAvailableWorkers()
    {
        $this->repository
            ->expects($this->any())
            ->method('find')
            ->will($this->returnValue(new ArrayIterator([])));
    }

    private function assertArrayAreEquals($expected, $given)
    {
        sort($expected);
        sort($given);
        $this->assertEquals($expected, $given);
    }
}
