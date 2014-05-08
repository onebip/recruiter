<?php

namespace Recruiter;

use MongoId;
use MongoDate;
use MongoInt32;
use Exception;

class Job
{
    private $toDo;
    private $recruiter;
    private $instantiatedAt;
    private $status;

    public static function around(Workable $toDo, Recruiter $recruiter)
    {
        return new self(self::initialize(), $toDo, $recruiter);
    }

    public static function import($document, Recruiter $recruiter)
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
            $recruiter
        );
    }

    public function __construct($status, Workable $toDo, Recruiter $recruiter)
    {
        $this->toDo = $toDo;
        $this->status = $status;
        $this->recruiter = $recruiter;
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
        if ($this->isSheduledLater()) {
            return $this->recruiter->schedule($this);
        }
        return $this->executeNow();
    }

    public function export()
    {
        return array_merge(
            $this->status, [
                'attempts' => new MongoInt32($this->status['attempts']),
                'workable_class' => get_class($this->toDo),
                'workable_parameters' => $this->toDo->export(),
                'workable_method' => 'execute',
            ]
        );
    }

    public function isActive()
    {
        return array_key_exists('active', $this->status) && $this->status['active'];
    }

    private function isSheduledLater()
    {
        return array_key_exists('scheduled_at', $this->status) &&
            ($this->instantiatedAt->sec <= $this->status['scheduled_at']->sec) &&
            ($this->instantiatedAt->usec <= $this->status['scheduled_at']->usec);
    }

    private function executeNow()
    {
        $methodToCallOnWorkable = $this->status['workable_method'];
        if (!method_exists($this->toDo, $methodToCallOnWorkable)) {
            throw new Exception('Unknown method on workable instance');
        }
        try {
            $this->beforeExecution();
            $result = $this->toDo->$methodToCallOnWorkable();
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
        $this->recruiter->accept($this);
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

    private function archive()
    {
        $this->status['active'] = false;
        $this->status['done'] = true;
        $this->recruiter->archive($this);
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
