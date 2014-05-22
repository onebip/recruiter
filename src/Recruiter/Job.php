<?php

namespace Recruiter;

use MongoId;
use MongoDate;
use MongoInt32;
use Exception;
use Recruiter\Job\Repository;

class Job
{
    private $toDo;
    private $recruiter;
    private $instantiatedAt;
    private $status;

    public static function around(Workable $toDo, Recruiter $recruiter, Repository $repository)
    {
        return new self(self::initialize(), $toDo, $recruiter, $repository);
    }

    public static function import($document, Recruiter $recruiter, Repository $repository)
    {
        if (!array_key_exists('workable_class', $document)) {
            throw new Exception('Unable to import Job without a class');
        }
        if (!class_exists($document['workable_class'])) {
            throw new Exception('Unable to import Job with unknown Workable class');
        }
        if (!method_exists($document['workable_class'], 'import')) {
            throw new Exception('Unable to import Workable without method import');
        }
        return new self(
            $document,
            $document['workable_class']::import($document['workable_parameters'], $recruiter),
            $recruiter,
            $repository
        );
    }

    public function __construct($status, Workable $toDo, Recruiter $recruiter, Repository $repository)
    {
        $this->workable = $toDo;
        $this->status = $status;
        $this->recruiter = $recruiter;
        $this->repository = $repository;
        $this->instantiatedAt = new MongoDate();
    }

    public function id()
    {
        return $this->status['_id'];
    }

    /* public function assignTo($worker) */
    /* { */
    /*     $this->status['worker_was_available_since'] = $worker->availableSince(); */
    /*     $worker->assignedTo($this); */
    /*     $this->lock(); */
    /* } */

    public function updateWith($document)
    {
        $this->status = self::fromMongoDocumentToInternalStatus($document);
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
                'workable_class' => get_class($this->workable),
                'workable_parameters' => $this->workable->export(),
                'workable_method' => 'execute',
            ]
        );
    }

    public function isActive()
    {
        return array_key_exists('active', $this->status) && $this->status['active'];
    }

    /* private function lock() */
    /* { */
    /*     $this->status['locked'] = true; */
    /*     $this->save(); */
    /* } */

    private function isScheduledLater()
    {
        return array_key_exists('scheduled_at', $this->status) &&
            ($this->instantiatedAt->sec <= $this->status['scheduled_at']->sec) &&
            ($this->instantiatedAt->usec <= $this->status['scheduled_at']->usec);
    }

    private function executeNow()
    {
        $methodToCall = $this->status['workable_method'];
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
            /* 'worker_was_idle_for_ms' => $this->msSince($this->status['worker_was_available_since']), */
            'scheduled_at' => $this->status['scheduled_at'],
            'started_at' => new MongoDate(),
            // TODO: informations on worker?
        ];
        unset($this->status['scheduled_at']);
        /* unset($this->status['worker_was_available_since']); */
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

    private function msSince(MongoDate $from)
    {
        $to = new MongoDate();
        $fromUSec = floatval("{$from->sec}.{$from->usec}");
        $toUSec = floatval("{$to->sec}.{$to->usec}");
        $diffUSec = $toUSec - $fromUSec;
        $diffMSec = round($diffUSec * 1000);
        return $diffMSec;
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
            'workable_method' => 'execute',
            'attempts' => 0,
            'locked' => false,
            'tags' => []
        ];
    }
}
