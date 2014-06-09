<?php

namespace Recruiter\RetryPolicy;

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
        $this->retriableExceptions = $retriableExceptions;
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


    private function isExceptionRetriable($exception)
    {
        return _::some($this->retriableExceptions, function($retriableExceptionType) use ($exception) {
            return ($exception instanceof $retriableExceptionType);
        });
    }
}
