<?php

namespace Recruiter;

class JobTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->workable = $this
            ->getMockBuilder('Recruiter\Workable')
            ->setMethods(['export', 'import', 'asJobOf', 'send'])
            ->getMock();

        $this->repository = $this
            ->getMockBuilder('Recruiter\Job\Repository')
            ->disableOriginalConstructor()
            ->getMock();

        $this->job = Job::around($this->workable, $this->repository);
    }

    public function testConfigureMethodToCallOnWorkable()
    {
        $this->workable->expects($this->once())->method('send');
        $this->job->methodToCallOnWorkable('send');
        $this->job->execute();
    }

    /**
     * @expectedException Exception
     */
    public function testRaiseExceptionWhenConfigureMethodToCallOnWorkableThatDoNotExists()
    {
        $this->job->methodToCallOnWorkable('methodThatDoNotExists');
    }
}
