<?php

namespace Recruiter\Worker;

use Exception;
use Recruiter\Worker\Repository;

class Tracker
{
    private $process;
    private $pidFilePath;

    public function __construct()
    {
        $this->pidFilePath = tempnam(sys_get_temp_dir(), 'recruiter');
        $this->ensureItIsPossibleToUse($this->pidFilePath);
    }

    public function associateTo($worker)
    {
        file_put_contents($this->pidFilePath, $worker->pid());
    }

    public function process()
    {
        return $this->process = $this->process ?: Process::withPid($this->workerPid($this->pidFilePath));
    }

    private function workerPid($fileWithWorkerPid)
    {
        if (!file_exists($fileWithWorkerPid)) {
            $this->failBecause('did you tried to get the worker in two different processes?');
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
