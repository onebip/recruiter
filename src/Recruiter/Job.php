<?php

namespace Recruiter;

use MongoId;
use MongoDate;
use Exception;

use Recruiter\RetryPolicy;
use Recruiter\Job\Repository;

class Job
{
    private $status;
    private $workable;
    private $retryPolicy;
    private $repository;

    public static function around(Workable $workable, Repository $repository)
    {
        return new self(
            self::initialize(),
            $workable,
            new RetryPolicy\DoNotDoItAgain(),
            $repository
        );
    }

    public static function import($document, Repository $repository)
    {
        return new self(
            $document,
            WorkableInJob::import($document),
            RetryPolicyInJob::import($document),
            $repository
        );
    }

    public function __construct($status, Workable $workable, RetryPolicy $retryPolicy, Repository $repository)
    {
        $this->status = $status;
        $this->workable = $workable;
        $this->retryPolicy = $retryPolicy;
        $this->repository = $repository;
    }

    public function id()
    {
        return $this->status['_id'];
    }

    public function numberOfAttempts()
    {
        return $this->status['attempts'];
    }

    public function retryWithPolicy(RetryPolicy $retryPolicy)
    {
        $this->retryPolicy = $retryPolicy;
    }

    public function scheduleAt(MongoDate $at)
    {
        $this->status['locked'] = false;
        $this->status['scheduled_at'] = $at;
    }

    public function methodToCallOnWorkable($method)
    {
        $this->status['workable']['method'] = $method;
    }

    public function schedule()
    {
        $this->save();
    }

    public function execute()
    {
        $methodToCall = $this->ensureMethodToCallExistsInWorkable();
        try {
            $this->beforeExecution();
            $result = $this->workable->$methodToCall();
            $this->afterExecution($result);
            return $result;
        } catch(\Exception $exception) {
            $this->afterFailure($exception);
        }
    }

    public function export()
    {
        return array_merge(
            $this->status,
            WorkableInJob::export($this->workable),
            RetryPolicyInJob::export($this->retryPolicy)
        );
    }

    private function ensureMethodToCallExistsInWorkable()
    {
        $methodToCall = $this->status['workable']['method'];
        if (!method_exists($this->workable, $methodToCall)) {
            throw new Exception("Unknown method '$methodToCall' on workable instance");
        }
        return $methodToCall;
    }

    private function beforeExecution()
    {
        $this->status['attempts'] += 1;
        $this->status['last_execution'] = [
            'started_at' => new MongoDate(),
        ];
        if ($this->hasBeenScheduled()) {
            $this->save();
        }
    }

    private function afterExecution($result)
    {
        $this->traceLastExecution($result);
        if ($this->hasBeenScheduled()) {
            $this->archive(true);
        }
    }

    private function afterFailure($exception)
    {
        $this->traceLastExecution($exception);
        $this->retryPolicy->schedule($this);
        $this->save();
        if (!$this->hasBeenScheduled()) {
            $this->archive(false);
        }
    }

    private function hasBeenScheduled()
    {
        return array_key_exists('scheduled_at', $this->status);
    }

    private function traceLastExecution($result)
    {
        $this->status['last_execution'] = array_merge(
            $this->status['last_execution'], [
                'ended_at' => new MongoDate(),
                'class' => is_object($result) ? get_class($result) : null,
                'message' => method_exists($result, 'getMessage') ? $result->getMessage() : null,
                'trace' => null
            ]
        );
    }

    private function save()
    {
        $this->repository->save($this);
    }

    private function archive($done)
    {
        $this->status['active'] = false;
        $this->status['locked'] = false;
        $this->status['done'] = $done;
        $this->repository->archive($this);
    }

    private static function initialize()
    {
        return array_merge(
            [
                '_id' => new MongoId(),
                'active' => true,
                'done' => false,
                'created_at' => new MongoDate(),
                'locked' => false,
                'attempts' => 0,
                'tags' => []
            ],
            WorkableInJob::initialize(),
            RetryPolicyInJob::initialize()
        );
    }
}
