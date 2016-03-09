<?php
namespace Recruiter\Acceptance;

use Recruiter\Recruiter;
use Recruiter\Workable\ShellCommand;
use MongoClient;
use Onebip\Concurrency\Timeout;

abstract class BaseAcceptanceTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->recruiterDb = (new MongoClient('localhost:27017'))->selectDB('recruiter');
        $this->cleanDb();
        $this->files = ['/tmp/recruiter.log', '/tmp/worker.log'];
        $this->cleanLogs();
        $this->roster = $this->recruiterDb->selectCollection('roster');
        $this->recruiter = new Recruiter($this->recruiterDb);
        $this->jobs = 0;
    }

    public function cleanDb()
    {
        $this->recruiterDb->drop();
    }

    public function cleanLogs()
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
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

    protected function startRecruiter()
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = __DIR__ . '/../../../';
        $process = proc_open('exec php bin/recruiter >> /tmp/recruiter.log 2>&1', $descriptors, $pipes, $cwd);
        Timeout::inSeconds(1, "recruiter to be up")
            ->until(function() use ($process) {
                $status = proc_get_status($process);
                return $status['running'];
            });
        return [$process, $pipes, 'recruiter'];
    }

    protected function startWorker()
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cwd = __DIR__ . '/../../../';
        $process = proc_open('exec php bin/worker --bootstrap=examples/bootstrap.php >> /tmp/worker.log 2>&1', $descriptors, $pipes, $cwd);
        Timeout::inSeconds(1, "worker to be up")
            ->until(function() use ($process) {
                $status = proc_get_status($process);
                return $status['running'];
            });
        // proc_get_status($process);
        return [$process, $pipes, 'worker'];
    }

    protected function stopProcessWithSignal(array $processAndPipes, $signal)
    {
        list($process, $pipes, $name) = $processAndPipes;
        proc_terminate($process, $signal);
        Timeout::inSeconds(30, 'termination of process')
            ->until(function() use ($process) {
                $status = proc_get_status($process);
                return $status['running'] == false;
            });
    }

    protected function enqueueJob()
    {
        $workable = ShellCommand::fromCommandLine('echo 42');
        $workable
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();
        $this->jobs++;
    }

}
