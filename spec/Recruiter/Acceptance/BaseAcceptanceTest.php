<?php

namespace Recruiter\Acceptance;

use Recruiter\Recruiter;
use MongoClient;
use Onebip\Concurrency\Timeout;

abstract class BaseAcceptanceTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->recruiterDb = (new MongoClient('localhost:27017'))->selectDB('recruiter');
        $this->recruiterDb->drop();
        $this->roster = $this->recruiterDb->selectCollection('roster');
        $this->recruiter = new Recruiter($this->recruiterDb);
    }

    protected function numberOfWorkers()
    {
        return $this->roster->count();
    }

    protected function waitForNumberOfWorkersToBe($expectedNumber)
    {
        Timeout::inSeconds(1, "workers to be $expectedNumber")
            ->until(function() use ($expectedNumber) {
                return $this->numberOfWorkers() == $expectedNumber;
            });
    }

    protected function startRecruiter($callback = null)
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = __DIR__ . '/../../../';
        $process = proc_open('exec php bin/recruiter', $descriptors, $pipes, $cwd);
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        Timeout::inSeconds(1, "recruiter to be up")
            ->until(function() use ($process) {
                $status = proc_get_status($process);
                return $status['running'];
            });
        if ($callback !== null) {
            $callback([$process, $pipes]);
        }
        return [$process, $pipes];
    }

    protected function startWorker($callback = null)
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = __DIR__ . '/../../../';
        $process = proc_open('exec php bin/worker --bootstrap=examples/bootstrap.php', $descriptors, $pipes, $cwd);
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        Timeout::inSeconds(1, "worker to be up")
            ->until(function() use ($process) {
                $status = proc_get_status($process);
                return $status['running'];
            });
        // proc_get_status($process);
        if ($callback !== null) {
            $callback([$process, $pipes]);
        }
        return [$process, $pipes];
    }

    protected function stopWorkerWithSignal(array $processAndPipes, $signal, $callback)
    {
        list($process, $pipes) = $processAndPipes;
        proc_terminate($process, $signal);
        Timeout::inSeconds(1, 'termination of worker')
            ->until(function() use ($process) {
                $status = proc_get_status($process);
                return $status['running'] == false;
            });
    }
}
