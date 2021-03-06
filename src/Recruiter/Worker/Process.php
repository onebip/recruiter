<?php

namespace Recruiter\Worker;

use Sink\BlackHole;
use Recruiter\Worker\Repository;

class Process
{
    private $pid;

    public static function withPid($pid)
    {
        return new self($pid);
    }

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

    public function ifDead()
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
