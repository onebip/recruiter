<?php

namespace Recruiter\RetryPolicy;

use Exception;

class RetriableExceptionFilterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->filteredRetryPolicy = $this->getMock('Recruiter\RetryPolicy');
    }

    public function testCallScheduleOnRetriableException()
    {
        $exception = $this->getMock('Exception');
        $classOfException = get_class($exception);
        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy, [$classOfException]);

        $this->filteredRetryPolicy
            ->expects($this->once())
            ->method('schedule');

        $filter->schedule($this->jobFailedWithException($exception));
    }

    public function testDoNotCallScheduleOnNonRetriableException()
    {
        $exception = $this->getMock('Exception');
        $classOfException = get_class($exception);
        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy, [$classOfException]);

        $this->filteredRetryPolicy
            ->expects($this->never())
            ->method('schedule');

        $filter->schedule($this->jobFailedWithException(new Exception('Test')));
    }

    public function testWhenExceptionIsNotRetriableThenArchiveTheJob()
    {
        $exception = $this->getMock('Exception');
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

        $filter = RetriableExceptionFilter::import($filter->export());
        $filter->schedule($this->jobFailedWithException(new Exception('Test')));
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
