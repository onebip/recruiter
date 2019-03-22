<?php

namespace Recruiter\RetryPolicy;

use Onebip;
use InvalidArgumentException;

use Recruiter\Job;
use Recruiter\RetryPolicy;
use Recruiter\JobAfterFailure;

class RetriableExceptionFilter implements RetryPolicy
{
    private $filteredRetryPolicy;
    private $retriableExceptions;

    private $retryPolicy;

    /**
     * @param string $exceptionClass  fully qualified class or interface name
     * @return self
     */
    public static function onlyFor($exceptionClass, RetryPolicy $retryPolicy)
    {
        return new self($retryPolicy, [$exceptionClass]);
    }

    public function __construct(RetryPolicy $filteredRetryPolicy, array $retriableExceptions = ['Exception'])
    {
        $this->filteredRetryPolicy = $filteredRetryPolicy;
        $this->retriableExceptions = $this->ensureAreAllExceptions($retriableExceptions);
    }

    public function schedule(JobAfterFailure $job)
    {
        if ($this->isExceptionRetriable($job->causeOfFailure())) {
            $this->filteredRetryPolicy->schedule($job);
        } else {
            $job->archive('non-retriable-exception');
        }
    }

    public function export(): array
    {
        return [
            'retriable_exceptions' => $this->retriableExceptions,
            'filtered_retry_policy' => [
                'class' => get_class($this->filteredRetryPolicy),
                'parameters' => $this->filteredRetryPolicy->export()
            ]
        ];
    }

    public static function import(array $parameters): RetryPolicy
    {
        $filteredRetryPolicy = $parameters['filtered_retry_policy'];
        $retriableExceptions = $parameters['retriable_exceptions'];
        return new self(
            $filteredRetryPolicy['class']::import($filteredRetryPolicy['parameters']),
            $retriableExceptions
        );
    }

    public function isLastRetry(Job $job): bool
    {
        return $this->filteredRetryPolicy->isLastRetry($job);
    }

    private function ensureAreAllExceptions($exceptions)
    {
        foreach ($exceptions as $exception) {
            if (!is_a($exception, 'Exception', true)) {
                throw new InvalidArgumentException(
                    "Only subclasses of Exception can be retriable exceptions, '{$exception}' is not"
                );
            }
        }
        return $exceptions;
    }

    private function isExceptionRetriable($exception)
    {
        if (!is_null($exception) && is_object($exception)) {
            return Onebip\array_some(
                $this->retriableExceptions,
                function ($retriableExceptionType) use ($exception) {
                    return ($exception instanceof $retriableExceptionType);
                }
            );
        }
        return false;
    }
}
