<?php

namespace Recruiter;

use Exception;
use Recruiter\RetryPolicy\RetriableExceptionFilter;
use Recruiter\JobAfterFailure;

trait RetryPolicyBehaviour
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

    public function schedule(JobAfterFailure $job)
    {
        throw new Exception('RetryPolicy::schedule(JobAfterFailure) need to be implemented');
    }

    public function export(): array
    {
        return $this->parameters;
    }

    public static function import(array $parameters): RetryPolicy
    {
        return new static($parameters);
    }
}
