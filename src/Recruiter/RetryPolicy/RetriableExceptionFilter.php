<?php

namespace Recruiter\RetryPolicy;

use InvalidArgumentException;
use Underscore\Underscore as _;

use Recruiter\RetryPolicy;
use Recruiter\JobAfterFailure;

class RetriableExceptionFilter implements RetryPolicy
{
    private $filteredRetryPolicy;
    private $retriableExceptions;

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

    public function export()
    {
        return [
            'retriable_exceptions' => $this->retriableExceptions,
            'filtered_retry_policy' => [
                'class' => get_class($this->filteredRetryPolicy),
                'parameters' => $this->filteredRetryPolicy->export()
            ]
        ];
    }

    public static function import($parameters)
    {
        $filteredRetryPolicy = $parameters['filtered_retry_policy'];
        $retriableExceptions = $parameters['retriable_exceptions'];
        return new self(
            $filteredRetryPolicy['class']::import($filteredRetryPolicy['parameters']),
            $retriableExceptions
        );
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
            return _::some($this->retriableExceptions, function($retriableExceptionType) use ($exception) {
                return ($exception instanceof $retriableExceptionType);
            });
        }
        return false;
    }
}
