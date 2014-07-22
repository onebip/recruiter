<?php

namespace Recruiter;

use MongoId;
use Exception;

class OnlyOne
{
    private $repository;
    private $processTable;
    private $token;
    private $pid;

    public function __construct($repository, $processTable)
    {
        $this->repository = $repository;
        $this->processTable = $processTable;
        $this->token = new MongoId();
        $this->pid = posix_getpid();
    }

    public function ensure()
    {
        $previousRecruiter = $this->repository->get();
        if (is_null($previousRecruiter)) {
            $this->initialize();
        } else {
            $this->lockInPlaceOf($previousRecruiter);
        }
    }

    private function initialize()
    {
        $initialized = $this->repository->initialize($this->token, $this->pid);
        if (!$initialized) {
            throw new AlreadyRunningException();
        }
    }

    private function lockInPlaceOf($previousRecruiter)
    {
        if ($this->processTable->isAlive($previousRecruiter['pid'])) {
            throw new AlreadyRunningException();
        }
        $locked = $this->repository->lock($this->token, $this->pid);
        if (!$locked) {
            throw new AlreadyRunningException();
        }
    }
}
