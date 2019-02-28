<?php

namespace Recruiter\RetryPolicy;

use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RetriableExceptionFilterTest extends TestCase
{
    public function setUp(): void
    {
        $this->filteredRetryPolicy = $this->createMock('Recruiter\RetryPolicy');
    }

    public function testCallScheduleOnRetriableException()
    {
        $exception = $this->createMock('Exception');
        $classOfException = get_class($exception);
        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy, [$classOfException]);

        $this->filteredRetryPolicy
            ->expects($this->once())
            ->method('schedule');

        $filter->schedule($this->jobFailedWithException($exception));
    }

    public function testDoNotCallScheduleOnNonRetriableException()
    {
        $exception = $this->createMock('Exception');
        $classOfException = get_class($exception);
        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy, [$classOfException]);

        $this->filteredRetryPolicy
            ->expects($this->never())
            ->method('schedule');

        $filter->schedule($this->jobFailedWithException(new Exception('Test')));
    }

    public function testWhenExceptionIsNotRetriableThenArchiveTheJob()
    {
        $exception = $this->createMock('Exception');
        $classOfException = get_class($exception);
        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy, [$classOfException]);

        $job = $this->jobFailedWithException(new Exception('Test'));
        $job->expects($this->once())
            ->method('archive')
            ->with('non-retriable-exception');

        $filter->schedule($job);
    }

    public function testAllExceptionsAreRetriableByDefault()
    {
        $this->filteredRetryPolicy
            ->expects($this->once())
            ->method('schedule');

        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy);
        $filter->schedule($this->jobFailedWithException(new Exception('Test')));
    }

    public function testJobFailedWithSomethingThatIsNotAnException()
    {
        $jobAfterFailure = $this->jobFailedWithException(null);
        $jobAfterFailure
            ->expects($this->once())
            ->method('archive');

        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy);
        $filter->schedule($jobAfterFailure);
    }

    public function testExportFilteredRetryPolicy()
    {
        $this->filteredRetryPolicy
            ->expects($this->once())
            ->method('export')
            ->will($this->returnValue(['key' => 'value']));

        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy);

        $this->assertEquals(
            [
                'retriable_exceptions' => ['Exception'],
                'filtered_retry_policy' =>  [
                    'class' => get_class($this->filteredRetryPolicy),
                    'parameters' => ['key' => 'value']
                ]
            ],
            $filter->export()
        );
    }

    public function testImportRetryPolicy()
    {
        $filteredRetryPolicy = new DoNotDoItAgain();
        $filter = new RetriableExceptionFilter($filteredRetryPolicy);
        $exported = $filter->export();

        $filter = RetriableExceptionFilter::import($exported);
        $filter->schedule($this->jobFailedWithException(new Exception('Test')));

        $this->assertEquals($exported, $filter->export());
    }

    public function testRetriableExceptionsThatAreNotExceptions()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Only subclasses of Exception can be retriable exceptions, 'StdClass' is not");
        $retryPolicy = new DoNotDoItAgain();
        $notAnExceptionClass = 'StdClass';
        new RetriableExceptionFilter($retryPolicy, [$notAnExceptionClass]);
    }


    private function jobFailedWithException($exception)
    {
        $jobAfterFailure = $this
            ->getMockBuilder('Recruiter\JobAfterFailure')
            ->disableOriginalConstructor()
            ->getMock();

        $jobAfterFailure
            ->expects($this->any())
            ->method('causeOfFailure')
            ->will($this->returnValue($exception));

        return $jobAfterFailure;
    }
}
