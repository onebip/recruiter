<?php

namespace Recruiter;

use Timeless as T;
use Exception;

class JobExecution
{
    private $scheduledAt;
    private $startedAt;
    private $endedAt;
    private $completedWith;
    private $failedWith;

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

    public function causeOfFailure()
    {
        return $this->failedWith;
    }

    public function export()
    {
        $exported = [];
        if ($this->startedAt) {
            $exported['scheduled_at'] = T\MongoDate::from($this->scheduledAt);
        }
        if ($this->startedAt) {
            $exported['started_at'] = T\MongoDate::from($this->startedAt);
        }
        if ($this->endedAt) {
            $exported['ended_at'] = T\MongoDate::from($this->endedAt);
        }
        if ($this->failedWith) {
            $exported['class'] = get_class($this->failedWith);
            $exported['message'] = $this->failedWith->getMessage();
            $exported['trace'] = $this->traceOf($this->completedWith);
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
            $trace = $result->getMessage();
        } else if (is_object($result) && method_exists($result, 'trace')) {
            $trace = $result->trace();
        } else if (is_object($result)) {
            $trace = get_class($result);
        } else if (is_string($result) || is_numeric($result)) {
            $trace = $result;
        }
        return substr($trace, 0, 512);
    }
}
