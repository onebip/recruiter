<?php

namespace Recruiter;

use Sink\BlackHole;
use Recruiter\Worker\Repository;

class WorkerProcess
{
    private $pid;

    public function __construct($pid)
    {
        $this->pid = $pid;
    }

    public function cleanUp(Repository $repository)
    {
        if (!$this->isAlive()) {
            $repository->retireWorkerWithPid($this->pid);
        }
    }

    public function ifNotAlive()
    {
        if ($this->isAlive()) {
            return new BlackHole();
        }
        return $this;
    }

    protected function isAlive()
    {
        return posix_kill($this->pid, 0);
    }
}
