<?php

namespace Recruiter;

use MongoClient;
use Onebip\Concurrency\Timeout;

abstract class BaseAcceptanceTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->recruiter = (new MongoClient('localhost:27017'))->selectDB('recruiter');
        $this->recruiter->drop();
        $this->roster = $this->recruiter->selectCollection('roster');
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

    protected function startWorker($callback)
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = __DIR__ . '/../../';
        $process = proc_open('php bin/worker --bootstrap=examples/bootstrap.php', $descriptors, $pipes, $cwd);
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        // proc_get_status($process);
        $callback([$process, $pipes]);
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
