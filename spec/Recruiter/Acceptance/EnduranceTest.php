<?php
namespace Recruiter\Acceptance;

use Recruiter\Workable\ShellCommand;
use Recruiter\Job\Repository;
use Onebip\Concurrency\Timeout;
use Eris;
use Eris\Generator;
use Eris\Listener;

class EnduranceTest extends BaseAcceptanceTest
{
    use Eris\TestTrait; 

    public function setUp()
    {
        parent::setUp();
        $this->jobRepository = new Repository($this->recruiterDb);
        $this->jobs = 0;
    }

    public function tearDown()
    {
        // TODO: try SIGKILL
        $this->terminateProcesses(SIGTERM);
    }
    
    public function testNotWithstandingCrashesJobsAreEventuallyPerformed()
    {

        $this
            ->limitTo(10)
            ->forAll(Generator\seq(Generator\elements([
                'enqueueJob',
                'restartWorker',
            ])))
            ->hook(Listener\collectFrequencies(function($actions) {
                return '[' . implode(',', $actions) . ']';
            }))
            ->then(function($actions) {
                $this->clean();
                $this->start();
                foreach ($actions as $action) {
                    $this->$action();
                }
                $estimatedTime = $this->jobs * 2;
                Timeout::inSeconds($estimatedTime, "all $this->jobs jobs to be performed (actions: " . var_export($actions, true) . ")")
                    ->until(function() {
                        return $this->jobRepository->countArchived() === $this->jobs;
                    });
            });
    }

    private function clean()
    {
        $this->terminateProcesses(SIGKILL);
        $this->cleanDb();
        $this->jobs = 0;
    }

    private function terminateProcesses($signal)
    {
        if ($this->processRecruiter) {
            $this->stopProcessWithSignal($this->processRecruiter, $signal);
            $this->processRecruiter = null;
        }
        if ($this->processWorker) {
            $this->stopProcessWithSignal($this->processWorker, $signal);
            $this->processWorker = null;
        }
    }

    private function start()
    {
        $this->processRecruiter = $this->startRecruiter();
        $this->processWorker = $this->startWorker();
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

    protected function restartWorker()
    {
        $this->stopProcessWithSignal($this->processWorker, SIGTERM);
        $this->processWorker = $this->startWorker();
    }
}
