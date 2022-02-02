<?php

namespace Recruiter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Exception;

class JobCallCustomMethodOnWorkableTest extends TestCase
{
    public function setUp(): void
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
        $this->job->execute($this->createMock(EventDispatcherInterface::class));
    }

    public function testRaiseExceptionWhenConfigureMethodToCallOnWorkableThatDoNotExists()
    {
        $this->expectException(Exception::class);

        $this->job->methodToCallOnWorkable('methodThatDoNotExists');
    }

    public function testCustomMethodIsSaved()
    {
        $this->job->methodToCallOnWorkable('send');
        $jobExportedToDocument = $this->job->export();
        $this->assertArrayHasKey('workable', $jobExportedToDocument);
        $this->assertArrayHasKey('method', $jobExportedToDocument['workable']);
        $this->assertEquals('send', $jobExportedToDocument['workable']['method']);
    }

    public function testCustomMethodIsConservedAfterImport()
    {
        $workable = new DummyWorkableWithSendCustomMethod();
        $job = Job::around($workable, $this->repository);
        $job->methodToCallOnWorkable('send');
        $jobExportedToDocument = $job->export();
        $jobImported = Job::import($jobExportedToDocument, $this->repository);
        $jobExportedToDocument = $job->export();
        $this->assertArrayHasKey('workable', $jobExportedToDocument);
        $this->assertArrayHasKey('method', $jobExportedToDocument['workable']);
        $this->assertEquals('send', $jobExportedToDocument['workable']['method']);
    }
}

class DummyWorkableWithSendCustomMethod extends BaseWorkable
{
    public function send()
    {
    }
}
