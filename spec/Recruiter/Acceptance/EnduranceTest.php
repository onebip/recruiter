<?php
namespace Recruiter\Acceptance;

use Recruiter\Job\Repository;
use Onebip\Concurrency\Timeout;
use Eris;
use Eris\Generator;
use Eris\Generator\ConstantGenerator;
use Eris\Listener;

/**
 * @group long
 */
class EnduranceTest extends BaseAcceptanceTest
{
    use Eris\TestTrait; 

    public function setUp()
    {
        parent::setUp();
        $this->jobRepository = new Repository($this->recruiterDb);
        $this->actionLog = '/tmp/actions.log';
        $this->files[] = $this->actionLog;
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
                                Generator\map(
                                    function($duration) {
                                        return ['enqueueJob', $duration];
                                    },
                                    Generator\nat()
                                ),
                                Generator\map(
                                    function($workerIndex) {
                                        return ['restartWorkerGracefully', $workerIndex];
                                    },
                                    Generator\choose(0, $workers - 1)
                                ),
                                Generator\map(
                                    function($workerIndex) {
                                        return ['restartWorkerByKilling', $workerIndex];
                                    },
                                    Generator\choose(0, $workers - 1)
                                ),
                                Generator\constant('restartRecruiterGracefully'),
                                Generator\constant('restartRecruiterByKilling'),
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
            ->hook(Listener\log('/tmp/recruiter-test-iterations.log'))
            ->hook(Listener\collectFrequencies())
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

                $estimatedTime = max(count($actions) * 4, 60);
                Timeout::inSeconds(
                    $estimatedTime,
                    function() {
                        return "all $this->jobs jobs to be performed. Now is " . date('c') . " Logs: " . $this->files();
                    }
                )
                    ->until(function() {
                        return $this->jobRepository->countArchived() === $this->jobs;
                    });

                $statistics = $this->recruiter->statistics();
                $this->assertEquals(0, $statistics['queued']);
                $this->assertGreaterThanOrEqual(0.0, $statistics['throughput']['value']);
                $this->assertGreaterThanOrEqual(0.0, $statistics['throughput']['value_per_second']);
                $this->assertGreaterThanOrEqual(0.0, $statistics['latency']['average']);
                $this->assertLessThan(60.0, $statistics['latency']['average']);
                $this->assertGreaterThanOrEqual(0.0, $statistics['execution_time']['average']);
                $this->assertLessThan(1.0, $statistics['execution_time']['average']);
                var_Dump($statistics);
            });
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

    protected function sleep($milliseconds)
    {
        usleep($milliseconds * 1000);
    }
}
