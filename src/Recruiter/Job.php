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
    private $lastJobExecution;

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
        $this->lastJobExecution = new JobExecution();
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
        if (!method_exists($this->workable, $method)) {
            throw new Exception("Unknown method '$method' on workable instance");
        }
        $this->status['workable']['method'] = $method;
    }

    public function execute()
    {
        $methodToCall = $this->status['workable']['method'];
        try {
            $this->beforeExecution();
            $result = $this->workable->$methodToCall();
            $this->afterExecution($result);
            return $result;
        } catch(\Exception $exception) {
            $this->afterFailure($exception);
        }
    }

    public function save()
    {
        $this->repository->save($this);
    }

    public function archive($why)
    {
        $this->status['why'] = $why;
        $this->status['active'] = false;
        $this->status['locked'] = false;
        unset($this->status['scheduled_at']);
        $this->repository->archive($this);
    }

    public function export()
    {
        return array_merge(
            $this->status,
            $this->lastJobExecution->export(),
            WorkableInJob::export($this->workable),
            RetryPolicyInJob::export($this->retryPolicy)
        );
    }

    private function beforeExecution()
    {
        $this->status['attempts'] += 1;
        $this->lastJobExecution->started();
        if ($this->hasBeenScheduled()) {
            $this->save();
        }
    }

    private function afterExecution($result)
    {
        $this->status['done'] = true;
        $this->lastJobExecution->completedWith($result);
        if ($this->hasBeenScheduled()) {
            $this->archive('done');
        }
    }

    private function afterFailure($exception)
    {
        $this->lastJobExecution->failedWith($exception);
        $jobAfterFailure = new JobAfterFailure($this, $this->lastJobExecution);
        $this->retryPolicy->schedule($jobAfterFailure);
        $jobAfterFailure->archiveIfNotScheduled();
    }

    private function hasBeenScheduled()
    {
        return array_key_exists('scheduled_at', $this->status);
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
