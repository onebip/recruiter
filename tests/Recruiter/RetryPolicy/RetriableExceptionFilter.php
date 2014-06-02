<?php

namespace Recruiter\RetryPolicy;

use Exception;

class RetriableExceptionFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testAllExceptionsAreRetriableByDefault()
    {
        $this->filteredRetryPolicy = $this->getMock('Recruiter\BaseRetryPolicy');

        $this->filter
            ->expects($this->once())
            ->method('schedule');

        $this->jobAfterFailure = $this
            ->getMockBuilder()
            ->disableOriginalConstructor()
            ->getMock();

        $this->jobAfterFailure
            ->expects($this->any())
            ->method('causeOfFailure')
            ->will($this->returnValue(new Exception('Test')));

        $filter = new RetriableExceptionFilter($this->filteredRetryPolicy);
        $filter->schedule($this->jobAfterFailure);
    }
}
