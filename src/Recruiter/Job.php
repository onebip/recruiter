<?php

namespace Recruiter;

use MongoId;
use MongoDate;
use MongoInt32;
use Exception;

use Recruiter\RetryPolicy;
use Recruiter\Job\Repository;

class Job
{
    private $status;
    private $workable;
    private $retryPolicy;
    private $instantiatedAt;

    public static function around(Workable $workable, Repository $repository)
    {
        return new self(self::initialize(), $workable, new RetryPolicy\DoNotDoItAgain(), $repository);
    }

    public static function import($document, Repository $repository)
    {
        return new self(
            $document,
            (new WorkableInJob())->import($document),
            (new RetryPolicyInJob())->import($document),
            $repository
        );
    }

    public function __construct($status, Workable $workable, RetryPolicy $retryPolicy, Repository $repository)
    {
        $this->status = $status;
        $this->workable = $workable;
        $this->retryPolicy = $retryPolicy;
        $this->repository = $repository;
        $this->instantiatedAt = new MongoDate();
    }

    public function id()
    {
        return $this->status['_id'];
    }

    public function scheduleTo()
    {
        $this->status['scheduled_at'] = new MongoDate();
        return $this;
    }

    public function retryWithPolicy(RetryPolicy $retryPolicy)
    {
        $this->retryPolicy = $retryPolicy;
        return $this;
    }

    public function numberOfAttempts()
    {
        return $this->status['attempts'];
    }

    public function scheduleAt(MongoDate $at)
    {
        $this->status['locked'] = false;
        $this->status['scheduled_at'] = $at;
        $this->save();
    }

    public function execute()
    {
        if ($this->isScheduledLater()) {
            return $this->schedule();
        }
        return $this->executeNow();
    }

    public function export()
    {
        return array_merge(
            $this->status,
            (new WorkableInJob())->export($this->workable),
            (new RetryPolicyInJob())->export($this->retryPolicy)
        );
    }

    public function isActive()
    {
        return array_key_exists('active', $this->status) && $this->status['active'];
    }

    private function executeNow()
    {
        $methodToCall = $this->status['workable']['method'];
        if (!method_exists($this->workable, $methodToCall)) {
            throw new Exception('Unknown method on workable instance');
        }
        try {
            $this->beforeExecution();
            $result = $this->workable->$methodToCall();
            $this->afterExecution($result);
            return $result;

        } catch(\Exception $exception) {
            $this->afterFailure($exception);
        }
    }

    private function beforeExecution()
    {
        $this->status['attempts'] += 1;
        $this->status['last_execution'] = [
            'scheduled_at' => $this->status['scheduled_at'],
            'started_at' => new MongoDate(),
        ];
        unset($this->status['scheduled_at']);
        $this->save();
    }

    private function afterExecution($result)
    {
        $this->traceLastExecution($result);
        $this->archive(true);
    }

    private function afterFailure($exception)
    {
        $this->traceLastExecution($exception);
        $this->retryPolicy->schedule($this);
        if (!array_key_exists('scheduled_at', $this->status)) {
            $this->archive(false);
        }
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

    private function isScheduledLater()
    {
        return array_key_exists('scheduled_at', $this->status) &&
            ($this->instantiatedAt->sec <= $this->status['scheduled_at']->sec) &&
            ($this->instantiatedAt->usec <= $this->status['scheduled_at']->usec);
    }

    private function schedule()
    {
        $this->repository->schedule($this);
    }

    private function archive($done)
    {
        $this->status['active'] = false;
        $this->status['locked'] = false;
        $this->status['done'] = $done;
        $this->repository->archive($this);
    }

    private function refresh()
    {
        $this->repository->refresh($this);
    }

    private function save()
    {
        $this->repository->save($this);
    }

    private static function fromMongoDocumentToInternalStatus($document)
    {
        return $document;
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
            (new WorkableInJob())->initialize(),
            (new RetryPolicyInJob())->initialize()
        );
    }
}
