<?php

namespace Recruiter;

use Recruiter\RetryPolicy\RetriableExceptionFilter;

trait Retriable
{
    private $parameters;

    public function __construct($parameters = [])
    {
        $this->parameters = $parameters;
    }

    public function retryOnlyWhenExceptionIs($retriableExceptionType)
    {
        return new RetriableExceptionFilter($this, [$retriableExceptionType]);
    }

    public function retryOnlyWhenExceptionsAre($retriableExceptionTypes)
    {
        return new RetriableExceptionFilter($this, $retriableExceptionTypes);
    }

    public function export()
    {
        return $this->parameters;
    }

    public static function import($parameters)
    {
        return new self($parameters);
    }
}
