<?php

namespace Recruiter;

use Exception;
use Recruiter\RetryPolicy;

class RetryPolicyInJob
{
    public static function import($document)
    {
        if (!array_key_exists('retry_policy', $document)) {
            throw new Exception('Unable to import Job without data about RetryPolicy object');
        }
        $dataAboutRetryPolicyObject = $document['retry_policy'];
        if (!array_key_exists('class', $dataAboutRetryPolicyObject)) {
            throw new Exception('Unable to import Job without a class');
        }
        if (!class_exists($dataAboutRetryPolicyObject['class'])) {
            throw new Exception('Unable to import Job with unknown RetryPolicy class');
        }
        if (!method_exists($dataAboutRetryPolicyObject['class'], 'import')) {
            throw new Exception('Unable to import RetryPolicy without method import');
        }
        return $dataAboutRetryPolicyObject['class']::import($dataAboutRetryPolicyObject['parameters']);
    }

    public static function export($retryPolicy)
    {
        return [
            'retry_policy' => [
                'class' => get_class($retryPolicy),
                'parameters' => $retryPolicy->export(),
            ]
        ];
    }

    public static function initialize()
    {
        return [];
    }
}
