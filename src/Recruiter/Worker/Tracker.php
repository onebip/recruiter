<?php

namespace Recruiter\Worker;

use Exception;
use Recruiter\Worker\Repository;

class Tracker
{
    private $workerPid;

    public function __construct()
    {
        $this->workerPidFilePath = tempnam(sys_get_temp_dir(), 'recruiter');
        $this->ensureItIsPossibleToUse($this->workerPidFilePath);
    }

    public function associateTo($worker)
    {
        file_put_contents($this->workerPidFilePath, $worker->pid());
    }

    public function cleanUp(Repository $repository)
    {
        Process::withPid($this->workerPid($this->workerPidFilePath))->ifDead()->cleanUp($repository);
    }

    private function workerPid($fileWithWorkerPid)
    {
        if (!file_exists($fileWithWorkerPid)) {
            $this->failBecause('did you call cleanUp twice? Don\'t do that :-)');
        }
        $workerPid = file_get_contents($fileWithWorkerPid);
        @unlink($fileWithWorkerPid);
        return $workerPid;
    }

    private function ensureItIsPossibleToUse($fileToUse)
    {
        $availableSpaceOnDevice = disk_free_space(dirname($fileToUse));
        if ($availableSpaceOnDevice < 1024) {
            $this->failBecause("not enough free space on device for file '$fileToUse'");
        }
        if (!is_writable($fileToUse) || !is_readable($fileToUse)) {
            $this->failBecause("could not read or write file'$fileToUse'");
        }
    }

    private function failBecause($reason)
    {
        throw new Exception("Unable to clean up after worker process death, {$reason}");
    }
}
