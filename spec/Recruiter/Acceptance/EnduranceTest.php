<?php
namespace Recruiter\Acceptance;

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
    }

    public function tearDown()
    {
        $this->terminateProcesses(SIGKILL);
    }
    
    public function testNotWithstandingCrashesJobsAreEventuallyPerformed()
    {
        $this->markTestSkipped();
        $this
            ->limitTo(20)
            ->forAll(Generator\seq(Generator\elements([
                'enqueueJob',
                'restartWorker',
                'restartRecruiter',
            ])))
            ->hook(Listener\collectFrequencies(function($actions) {
                return '[' . implode(',', $actions) . ']';
            }))
            ->then(function($actions) {
                $this->clean();
                $this->start();
                foreach ($actions as $action) {
                    $this->output .= "[ACTION_BEGIN][PHPUNIT][...] $action" . PHP_EOL;
                    $this->$action();
                    $this->drainOutput();
                }

                $estimatedTime = count($actions) * 3;
                $details = [
                    //'actions' => $actions,
                    'output' => $this->output,
                ];
                Timeout::inSeconds($estimatedTime, "all $this->jobs jobs to be performed. " . var_export($details, true))
                    ->until(function() {
                        $this->drainOutput();
                        return $this->jobRepository->countArchived() === $this->jobs;
                    });
            });
    }

    private function clean()
    {
        $this->terminateProcesses(SIGKILL);
        $this->cleanDb();
        $this->output = '';
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

    private function drainOutput()
    {
        $this->output .= stream_get_contents($this->processRecruiter[1][1]);
        $this->output .= stream_get_contents($this->processRecruiter[1][2]);
        $this->output .= stream_get_contents($this->processWorker[1][1]);
        $this->output .= stream_get_contents($this->processWorker[1][2]);
    }

    private function start()
    {
        $this->processRecruiter = $this->startRecruiter();
        $this->processWorker = $this->startWorker();
    }

    protected function restartWorker()
    {
        $this->stopProcessWithSignal($this->processWorker, SIGTERM);
        $this->drainOutput();
        $this->processWorker = $this->startWorker();
    }

    protected function restartRecruiter()
    {
        $this->stopProcessWithSignal($this->processRecruiter, SIGTERM);
        $this->drainOutput();
        $this->processRecruiter = $this->startRecruiter();
    }
}
