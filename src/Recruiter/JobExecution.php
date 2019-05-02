<?php

namespace Recruiter;

use Timeless as T;
use Exception;

class JobExecution
{
    private $isCrashed;
    private $scheduledAt;
    private $startedAt;
    private $endedAt;
    private $completedWith;
    private $failedWith;

    public function isCrashed()
    {
        return $this->isCrashed;
    }

    public function started($scheduledAt = null)
    {
        $this->scheduledAt = $scheduledAt;
        $this->startedAt = T\now();
    }

    public function failedWith(Exception $exception)
    {
        $this->endedAt = T\now();
        $this->failedWith = $exception;
    }

    public function completedWith($result)
    {
        $this->endedAt = T\now();
        $this->completedWith = $result;
    }

    public function result()
    {
        return $this->completedWith;
    }

    public function causeOfFailure()
    {
        return $this->failedWith;
    }

    public function isFailed()
    {
        return !is_null($this->failedWith) || $this->isCrashed();
    }

    public function duration()
    {
        if ($this->startedAt && $this->endedAt && ($this->startedAt <= $this->endedAt)) {
            return T\seconds(
                $this->endedAt->seconds() -
                $this->startedAt-> seconds()
            );
        }
        return T\seconds(0);
    }

    public static function import($document)
    {
        $lastExecution = new self();
        if (array_key_exists('last_execution', $document)) {
            $lastExecutionDocument = $document['last_execution'];
            if (array_key_exists('crashed', $lastExecutionDocument)) {
                $lastExecution->isCrashed = true;
            }
            if (array_key_exists('scheduled_at', $lastExecutionDocument)) {
                $lastExecution->scheduledAt = T\MongoDate::toMoment($lastExecutionDocument['scheduled_at']);
            }
            if (array_key_exists('started_at', $lastExecutionDocument)) {
                $lastExecution->startedAt = T\MongoDate::toMoment($lastExecutionDocument['started_at']);
            }
        }
        return $lastExecution;
    }

    public function export()
    {
        $exported = [];
        if ($this->scheduledAt) {
            $exported['scheduled_at'] = T\MongoDate::from($this->scheduledAt);
        }
        if ($this->startedAt) {
            $exported['started_at'] = T\MongoDate::from($this->startedAt);
        }
        if ($this->endedAt && !$this->isCrashed) {
            $exported['ended_at'] = T\MongoDate::from($this->endedAt);
        }
        if ($this->failedWith) {
            $exported['class'] = get_class($this->failedWith);
            $exported['message'] = $this->failedWith->getMessage();
            $exported['trace'] = $this->traceOf($this->failedWith);
        }
        if ($this->completedWith) {
            $exported['trace'] = $this->traceOf($this->completedWith);
        }
        if ($exported) {
            return ['last_execution' => $exported];
        } else {
            return [];
        }
    }

    private function traceOf($result)
    {
        $trace = 'ok';
        if ($result instanceof \Exception) {
            $trace = $result->getTraceAsString();
        } elseif (is_object($result) && method_exists($result, 'trace')) {
            $trace = $result->trace();
        } elseif (is_object($result)) {
            $trace = get_class($result);
        } elseif (is_string($result) || is_numeric($result)) {
            $trace = $result;
        }
        return substr($trace, 0, 512);
    }
}
