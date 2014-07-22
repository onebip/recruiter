<?php

namespace Recruiter;

use Exception;

class OnlyOneTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->aToken = 1;
        $this->aPid = 1;
        $this->repository = $this->getMock('StdClass', ['get', 'initialize', 'lock']);
        $this->processTable = $this->getMock('StdClass', ['isAlive']);
        $this->onlyOne = new OnlyOne($this->repository, $this->processTable);
    }

    public function testIsOkWhenItIsTheFirst()
    {
        $this->lockDocumentIsNotFound();
        $this->initializeIsCalled();

        $this->onlyOne->ensure();
    }

    public function testIsOkWhenThePreviousRecruiterIsDead()
    {
        $this->lockDocumentIsFound();
        $this->previousRecruiterIsNotAlive();
        $this->lockIsCalled();

        $this->onlyOne->ensure();
    }

    /**
     * @expectedException Recruiter\AlreadyRunningException
     */
    public function testRaiseWhenIsNotTheOnlyOne()
    {
        $this->lockDocumentIsFound();
        $this->previousRecruiterIsStillAlive();

        $this->onlyOne->ensure();
    }

    /**
     * @expectedException Recruiter\AlreadyRunningException
     */
    public function testRaiseWhenAnotherRecruiterWinTheRaceToInizialize()
    {
        $this->lockDocumentIsNotFound();
        $this->initializeFailsBecauseAnotherRecruiterInitializeInTheMeantime();

        $this->onlyOne->ensure();
    }

    /**
     * @expectedException Recruiter\AlreadyRunningException
     */
    public function testRaiseWhenAnotherRecruiterWinTheRaceToLock()
    {
        $this->lockDocumentIsFound();
        $this->previousRecruiterIsNotAlive();
        $this->lockFailsBecauseAnotherRecruiterLocksInTheMeantime();

        $this->onlyOne->ensure();
    }



    private function initializeFailsBecauseAnotherRecruiterInitializeInTheMeantime()
    {
        $this->repository
            ->expects($this->atLeastOnce())
            ->method('initialize')
            ->will($this->returnValue(false));
    }

    private function lockFailsBecauseAnotherRecruiterLocksInTheMeantime()
    {
        $this->repository
            ->expects($this->atLeastOnce())
            ->method('lock')
            ->will($this->returnValue(false));
    }

    private function lockDocumentIsNotFound()
    {
        $this->repository->expects($this->atLeastOnce())->method('get')->will($this->returnValue(null));
    }

    private function lockDocumentIsFound()
    {
        $this->repository
            ->expects($this->atLeastOnce())
            ->method('get')
            ->will($this->returnValue(['token' => $this->aToken, 'pid' => $this->aPid]));
    }

    private function initializeIsCalled()
    {
        $this->repository
            ->expects($this->atLeastOnce())
            ->method('initialize')
            ->will($this->returnValue(true));
    }

    private function lockIsCalled()
    {
        $this->repository
            ->expects($this->atLeastOnce())
            ->method('lock')
            ->will($this->returnValue(true));
    }

    private function previousRecruiterIsStillAlive()
    {
        $this->processTable
            ->expects($this->any())
            ->method('isAlive')
            ->with($this->aPid)
            ->will($this->returnValue(true));
    }

    private function previousRecruiterIsNotAlive()
    {
        $this->processTable
            ->expects($this->any())
            ->method('isAlive')
            ->with($this->aPid)
            ->will($this->returnValue(false));
    }
}
