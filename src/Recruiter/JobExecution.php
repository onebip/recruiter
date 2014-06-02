<?php

namespace Recruiter;

use Timeless;
use Exception;

class JobExecution
{
    private $startedAt;
    private $endedAt;
    private $completedWith;
    private $failedWith;

    public function started()
    {
        $this->startedAt = Timeless\now();
    }

    public function failedWith(Exception $exception)
    {
        $this->endedAt = Timeless\now();
        $this->failedWith = $exception;
    }

    public function completedWith($result)
    {
        $this->endedAt = Timeless\now();
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
            $exported['started_at'] = $this->startedAt->to('MongoDate');
            if ($this->endedAt) {
                $exported['ended_at'] = $this->startedAt->to('MongoDate');
                if ($this->failedWith) {
                    $exported['class'] = get_class($this->failedWith);
                    $exported['message'] = $this->failedWith->getMessage();
                    $exported['trace'] = null; // TODO
                }
                if ($this->completedWith) {
                    $exported['trace'] = null; // TODO
                }
            }
            return ['last_execution' => $exported];
        }
        return $exported;
    }
}
