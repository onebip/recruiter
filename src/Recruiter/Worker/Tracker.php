<?php

namespace Recruiter\Worker;

use Exception;
use Recruiter\Worker\Repository;

class Tracker
{
    private $workerId;

    public function __construct()
    {
        $this->workerIdFilePath = tempnam(sys_get_temp_dir(), 'recruiter');
        $this->ensureItIsPossibleToUse($this->workerIdFilePath);
    }

    public function associatedTo($worker)
    {
        file_put_contents($this->workerIdFilePath, $worker->id());
    }

    public function cleanUp(Repository $repository)
    {
        $repository->retire($this->workerId($this->workerIdFilePath));
    }

    private function workerId($fileWithWorkerId)
    {
        if (!file_exists($fileWithWorkerId)) {
            $this->failBecause('did you call cleanUp twice? Don\'t do that :-)');
        }
        $workerId = file_get_contents($fileWithWorkerId);
        @unlink($fileWithWorkerId);
        return $workerId;
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
