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
    private $recruiter;
    private $instantiatedAt;

    public static function around(Workable $workable, Recruiter $recruiter, Repository $repository)
    {
        return new self(self::initialize(), $workable, $recruiter, $repository);
    }

    public static function import($document, Recruiter $recruiter, Repository $repository)
    {
        if (!array_key_exists('workable', $document)) {
            throw new Exception('Unable to import Job without data about Workable object');
        }
        $dataAboutWorkableObject = $document['workable'];
        if (!array_key_exists('class', $dataAboutWorkableObject)) {
            throw new Exception('Unable to import Job without a class');
        }
        if (!class_exists($dataAboutWorkableObject['class'])) {
            throw new Exception('Unable to import Job with unknown Workable class');
        }
        if (!method_exists($dataAboutWorkableObject['class'], 'import')) {
            throw new Exception('Unable to import Workable without method import');
        }
        return new self(
            $document,
            $dataAboutWorkableObject['class']::import(
                $dataAboutWorkableObject['parameters'], new RetryPolicy\DoNotDoItAgain()
            ),
            $recruiter,
            $repository
        );
    }

    public function __construct($status, Workable $workable, Recruiter $recruiter, Repository $repository)
    {
        $this->status = $status;
        $this->workable = $workable;
        $this->recruiter = $recruiter;
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
            $this->status, [
                'attempts' => new MongoInt32($this->status['attempts']),
                'workable' => [
                    'class' => get_class($this->workable),
                    'parameters' => $this->workable->export(),
                    'method' => 'execute',
                ],
            ]
        );
    }

    public function isActive()
    {
        return array_key_exists('active', $this->status) && $this->status['active'];
    }

    private function isScheduledLater()
    {
        return array_key_exists('scheduled_at', $this->status) &&
            ($this->instantiatedAt->sec <= $this->status['scheduled_at']->sec) &&
            ($this->instantiatedAt->usec <= $this->status['scheduled_at']->usec);
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
            // TODO: informations on worker?
        ];
        unset($this->status['scheduled_at']);
        $this->save();
    }

    private function afterExecution($result)
    {
        $this->status['last_execution'] = array_merge(
            $this->status['last_execution'], [
                'ended_at' => new MongoDate(),
                // TODO: result class, message and trace
            ]
        );
        $this->archive();
    }

    private function afterFailure($exception)
    {
        // TODO: apply retry policy
        $this->archive();
    }

    private function schedule()
    {
        $this->repository->schedule($this);
    }

    private function archive()
    {
        $this->status['active'] = false;
        $this->status['done'] = true;
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
        return [
            '_id' => new MongoId(),
            'active' => true,
            'done' => false,
            'created_at' => new MongoDate(),
            'workable' => ['method' => 'execute'],
            'attempts' => 0,
            'locked' => false,
            'tags' => []
        ];
    }
}
