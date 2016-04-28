<?php
namespace Recruiter\Job;

use Recruiter\Job;
use Recruiter\Factory;
use Recruiter\JobToSchedule;
use DateTime;
use Timeless as T;

class RepositoryTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $factory = new Factory();
        $this->recruiterDb = $factory->getMongoDb(
            $hosts = 'localhost:27017',
            $options = [],
            $dbName = 'recruiter'
        );
        $this->recruiterDb->drop();
        $this->repository = new Repository($this->recruiterDb);
        $this->clock = T\clock()->stop();
    }

    public function tearDown()
    {
        T\clock()->start();
    }

    public function testCountsQueuedJobsAsOfNow()
    {
        $this->aJobToSchedule()->inGroup('generic')->inBackground()->execute();
        $this->aJobToSchedule()->inGroup('generic')->inBackground()->execute();
        $this->aJobToSchedule()->inGroup('fast-lane')->inBackground()->execute();
        $this->assertEquals(3, $this->repository->queued());
        $this->assertEquals(2, $this->repository->queued('generic'));
        $this->assertEquals(1, $this->repository->queued('fast-lane'));
    }

    public function testCountsQueuedJobsWithCornerCaseTagging()
    {
        $this->aJobToSchedule()->inBackground()->execute();
        $this->aJobToSchedule()->inGroup([])->inBackground()->execute();
        $this->aJobToSchedule()->inGroup('')->inBackground()->execute();
        $this->aJobToSchedule()->inGroup(null)->inBackground()->execute();

        $this->assertEquals(4, $this->repository->queued('generic'));
    }

    public function testCountsQueudJobsWithScheduledAtGreatherThanASpecificDate()
    {
        $this->aJobToSchedule()->inBackground()->execute();
        $time1 = $this->clock->now();
        $this->clock->driftForwardBySeconds(25 * 60 * 60);
        $this->aJobToSchedule()->inBackground()->execute();
        $this->assertEquals(
            1,
            $this->repository->queued(
                'generic',
                T\now(),
                T\now()->before(T\hour(24))
            )
        );
    }

    public function testCountsPostponedJobs()
    {
        $this->aJobToSchedule()->inBackground()->execute();
        $this->aJobToSchedule()->scheduleIn(T\hour(24))->execute();
        $this->assertEquals(1, $this->repository->postponed('generic'));
    }

    public function testRecentHistory()
    {
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->assertSame(
            [
                'throughput' => [
                    'value' => 3.0,
                    'value_per_second' => 3/60.0,
                ],
                'latency' => [
                    'average' => 5.0,
                ],
                'execution_time' => [
                    'average' => 0.0,
                ],
            ],
            $this->repository->recentHistory()
        );
    }

    public function testCleanOldArchived()
    {
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->assertEquals(3, $this->repository->cleanArchived(T\now()));
        $this->assertEquals(0, $this->repository->countArchived());
    }

    public function testCleaningOfOldArchivedCanBeLimitedByTime()
    {
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $time1 = $this->clock->now();
        $this->clock->driftForwardBySeconds(2 * 60 * 60);
        $this->repository->archive($this->aJob()->beforeExecution()->afterExecution(42));
        $this->assertEquals(2, $this->repository->cleanArchived($time1));
        $this->assertEquals(1, $this->repository->countArchived());
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
