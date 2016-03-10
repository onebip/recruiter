<?php
namespace Recruiter\Acceptance;

use Recruiter\Job\Repository;
use Onebip\Concurrency\Timeout;
use Eris;
use Eris\Generator;
use Eris\Generator\ConstantGenerator;
use Eris\Listener;

class EnduranceTest extends BaseAcceptanceTest
{
    use Eris\TestTrait; 

    public function setUp()
    {
        parent::setUp();
        $this->jobRepository = new Repository($this->recruiterDb);
        $this->actionLog = '/tmp/actions.log';
        $this->files[] = $this->actionLog;
        $this->processRecruiter = null;
        $this->processWorkers = [];
    }

    public function tearDown()
    {
        $this->terminateProcesses(SIGKILL);
    }
    
    public function testNotWithstandingCrashesJobsAreEventuallyPerformed()
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generator\bind(
                    Generator\choose(1, 4),
                    function($workers) {
                        return Generator\tuple(
                            Generator\constant($workers),
                            Generator\seq(Generator\oneOf(
                                Generator\constant('enqueueJob'),
                                Generator\map(
                                    function($workerIndex) {
                                        return ['restartWorker', $workerIndex];
                                    },
                                    Generator\choose(0, $workers - 1)
                                ),
                                Generator\constant('restartRecruiter'),
                                Generator\map(
                                    function($milliseconds) {
                                        return ['sleep', $milliseconds];
                                    },
                                    Generator\choose(1, 1000)
                                )
                            ))
                        );
                    }
                )
            )
            ->hook(Listener\collectFrequencies(function($actions) {
                // TODO: upgrade Eris and remove this custom function
                return json_encode($actions);
            }))
            ->then(function($tuple) {
                list ($workers, $actions) = $tuple;
                $this->clean();
                $this->start($workers);
                foreach ($actions as $action) {
                    $this->logAction($action);
                    if (is_array($action)) {
                        $arguments = $action;
                        $method = array_shift($arguments);
                        call_user_func_array(
                            [$this, $method],
                            $arguments
                        );
                    } else {
                        $this->$action();
                    }
                }

                $estimatedTime = count($actions) * 3;
                Timeout::inSeconds(
                    $estimatedTime,
                    function() {
                        return "all $this->jobs jobs to be performed. Now is " . date('c') . " See: " . var_export($this->files, true);
                    }
                )
                    ->until(function() {
                        return $this->jobRepository->countArchived() === $this->jobs;
                    });
            });
    }

    private function clean()
    {
        $this->terminateProcesses(SIGKILL);
        $this->cleanLogs();
        $this->cleanDb();
        $this->jobs = 0;
    }

    private function logAction($action)
    {
        file_put_contents(
            $this->actionLog,
            sprintf(
                "[ACTIONS][PHPUNIT][%s] %s" . PHP_EOL,
                date('c'), json_encode($action)
            ),
            FILE_APPEND
        );
    }

    private function terminateProcesses($signal)
    {
        if ($this->processRecruiter) {
            $this->stopProcessWithSignal($this->processRecruiter, $signal);
            $this->processRecruiter = null;
        }
        foreach ($this->processWorkers as $processWorker) {
            $this->stopProcessWithSignal($processWorker, $signal);
        }
        $this->processWorkers = [];
    }

    private function start($workers)
    {
        $this->processRecruiter = $this->startRecruiter();
        $this->processWorkers = [];
        for ($i = 0; $i < $workers; $i++) {
            $this->processWorkers[$i] = $this->startWorker();
        }
    }

    protected function restartWorker($workerIndex)
    {
        $this->stopProcessWithSignal($this->processWorkers[$workerIndex], SIGTERM);
        $this->processWorkers[$workerIndex] = $this->startWorker();
    }

    protected function restartRecruiter()
    {
        $this->stopProcessWithSignal($this->processRecruiter, SIGTERM);
        $this->processRecruiter = $this->startRecruiter();
    }

    protected function sleep($milliseconds)
    {
        usleep($milliseconds * 1000);
    }
}
