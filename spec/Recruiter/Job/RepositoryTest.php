<?php
namespace Recruiter\Job;

use Recruiter\Job;
use Recruiter\JobToSchedule;
use MongoClient;
use DateTime;
use Timeless as T;

class RepositoryTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->recruiterDb = (new MongoClient('localhost:27017'))->selectDB('recruiter');
        $this->recruiterDb->drop();
        $this->repository = new Repository($this->recruiterDb);
    }

    public function testCountsQueuedJobsAsOfNow()
    {
        $this->aJobToSchedule()->taggedAs('generic')->inBackground()->execute();
        $this->aJobToSchedule()->taggedAs('generic')->inBackground()->execute();
        $this->aJobToSchedule()->taggedAs('fast-lane')->inBackground()->execute();
        $this->assertEquals(3, $this->repository->queued());
        $this->assertEquals(2, $this->repository->queued('generic'));
        $this->assertEquals(1, $this->repository->queued('fast-lane'));
    }

    public function testRecentHistory()
    {
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->assertEquals(
            [
                'throughput' => [
                    'value' => 3,
                    'value_per_second' => 3/60.0,
                ],
                'latency' => [
                    'average' => 5,
                ],
                'execution_time' => [
                    'average' => 0,
                ],
            ],
            $this->repository->recentHistory()
        );
    }

    private function aJob()
    {
        $workable = $this
            ->getMockBuilder('Recruiter\Workable')
            ->getMock();

        return Job::around($workable, $this->repository)
            ->scheduleAt(T\now()->before(T\seconds(5)));
    }

    private function aJobToSchedule()
    {
        return new JobToSchedule($this->aJob());
    }
}
