<?php
namespace Recruiter\Acceptance;

use Recruiter\Workable\ShellCommand;
use Recruiter\Job\Repository;
use Onebip\Concurrency\Timeout;
use Eris;
use Eris\Generator;

class EnduranceTest extends BaseAcceptanceTest
{
    use Eris\TestTrait; 

    public function setUp()
    {
        parent::setUp();
        $this->jobRepository = new Repository($this->recruiterDb);
        $this->processRecruiter = $this->startRecruiter();
        $this->processWorker = $this->startWorker();
    }

    public function tearDown()
    {
        $this->stopProcessWithSignal($this->processRecruiter, SIGTERM);
        $this->stopProcessWithSignal($this->processWorker, SIGTERM);
    }
    
    public function testNotWithstandingCrashesJobsAreEventuallyPerformed()
    {
        $this
            ->limitTo(10)
            ->forAll(Generator\elements([
                'enqueueJob',
            ]))
            ->then(function($action) {
                $this->$action();
            });
        Timeout::inSeconds(30, "all 10 jobs to be performed")
            ->until(function() {
                return $this->jobRepository->countArchived() == 10;
            });
    }

    protected function enqueueJob()
    {
        $workable = ShellCommand::fromCommandLine('echo 42');
        $workable
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();
    }
}
