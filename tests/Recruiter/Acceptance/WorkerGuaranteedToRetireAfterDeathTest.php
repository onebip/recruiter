<?php

namespace Recruiter;

use MongoClient;

class WorkerGuaranteedToRetireAfterDeathTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @group acceptance
     */
    public function testRetireAfterAskedToStop()
    {
        $numberOfWorkersBefore = $this->numberOfWorkers();
        $this->startWorker(function($process) use ($numberOfWorkersBefore) {
            $this->assertEquals($numberOfWorkersBefore + 1, $this->numberOfWorkers());
            $this->stopWorkerWithSignal($process, SIGTERM, function($stdout, $stderr) use ($numberOfWorkersBefore) {
                $this->assertEquals($numberOfWorkersBefore, $this->numberOfWorkers());
            });
        });
    }

    public function setUp()
    {
        $this->recruiter = (new MongoClient('localhost:27017'))->selectDB('recruiter');
        $this->roster = $this->recruiter->selectCollection('roster');
    }

    protected function numberOfWorkers()
    {
        return $this->roster->count();
    }

    protected function startWorker($callback)
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = __DIR__ . '/../../../';
        $process = proc_open('php bin/worker --bootstrap=examples/bootstrap.php', $descriptors, $pipes, $cwd);
        stream_set_blocking($pipes[1], 0);
        stream_set_blocking($pipes[2], 0);
        usleep(100000);
        $callback([$process, $pipes]);
    }

    protected function stopWorkerWithSignal($process, $signal, $callback)
    {
        list($process, $pipes) = $process;
        proc_terminate($process, $signal);
        usleep(100000); // Wait until the signal will be dispatched by the supervisor process
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $callback($stdout, $stderr);
    }
}
