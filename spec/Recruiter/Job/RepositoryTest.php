<?php
namespace Recruiter\Job;

use Recruiter\Job;
use Recruiter\Factory;
use Recruiter\JobToSchedule;
use DateTime;
use Timeless as T;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
        $this->eventDispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
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
        $ed = $this->eventDispatcher;
        $this->repository->archive($this->aJob()->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->archive($this->aJob()->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->archive($this->aJob()->beforeExecution($ed)->afterExecution(42, $ed));
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

    public function testCountsQueuedJobsGroupingByASpecificKeyword()
    {
        $workable1 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();

        $workable2 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();

        $workable1
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['seller' => 'seller1']));

        $workable2
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['seller' => 'seller2']));

        $job1 = $this->aJob($workable1);
        $job2 = $this->aJob($workable2);
        $job3 = $this->aJob($workable2);

        $this->aJobToSchedule($job1)->inGroup('generic')->inBackground()->execute();
        $this->aJobToSchedule($job2)->inGroup('generic')->inBackground()->execute();
        $this->aJobToSchedule($job3)->inGroup('generic')->inBackground()->execute();
        $this->assertEquals(
            [
                'seller1' => '1',
                'seller2' => '2',
            ],
            $this->repository->queuedGroupedBy('workable.parameters.seller', [])
        );
    }

    public function testGetExpiredButStillScheduledJobs()
    {
        $workable1 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();

        $workable1
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['job1' => 'expired_and_unpicked']));

        $workable2 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();

        $workable2
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['job2' => 'expired_and_unpicked']));
        $workable3 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();
        $workable3
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['job3' => 'in_schedulation']));
        $this->aJobToSchedule($this->aJob($workable1))->inBackground()->execute();
        $this->aJobToSchedule($this->aJob($workable2))->inBackground()->execute();
        $this->clock->now();
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $this->aJobToSchedule($this->aJob($workable3))->inBackground()->execute();
        $jobs = $this->repository->expiredButStillScheduledJobs();
        foreach ($jobs as $job) {
            $this->assertEquals('expired_and_unpicked', reset($job->export()['workable']['parameters']));
        }
    }

    public function testCountExpiredButStillScheduledJobs()
    {
        $this->aJobToSchedule($this->aJob())->inBackground()->execute();
        $this->aJobToSchedule($this->aJob())->inBackground()->execute();
        $this->clock->now();
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $this->aJobToSchedule($this->aJob())->inBackground()->execute();
        $this->assertEquals(2, $this->repository->countExpiredButStillScheduledJobs());
    }

    public function testCountRecentJobsWithManyAttempts()
    {
        $ed = $this->eventDispatcher;
        $this->repository->archive($this->aJob()->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $this->clock->now();
        $threeHoursInSeconds = 3*60*60;
        $this->clock->driftForwardBySeconds($threeHoursInSeconds);
        $from = $this->clock->now(); 
        $this->repository->archive($this->aJob()->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->archive($this->aJob()->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $this->aJobToSchedule($this->aJob()->beforeExecution($ed)->beforeExecution($ed))->inBackground()->execute();
        $this->aJobToSchedule($this->aJob()->beforeExecution($ed)->beforeExecution($ed))->inBackground()->execute();
        $this->aJobToSchedule($this->aJob())->inBackground()->execute();
        $this->assertEquals(4, $this->repository->countRecentJobsWithManyAttempts($from));
    }

    public function testGetRecentJobsWithManyAttempts()
    {
        $ed = $this->eventDispatcher;
        $workable1 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();

        $workable1
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['job1' => 'many_attempts_and_archived_but_too_old']));

        $workable2 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();

        $workable2
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['job2' => 'many_attempts_and_archived']));

        $workable3 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();
        $workable3
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['job3' => 'many_attempts_and_archived']));

        $workable4 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();
        $workable4
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['job4' => 'many_attempts_and_scheduled']));
        $workable5 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();
        $workable5
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['job5' => 'many_attempts_and_scheduled']));
        $workable6 =  $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();
        $workable6
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue(['job6' => 'one_attempt_and_scheduled']));
        $this->repository->archive($this->aJob($workable1)->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $this->clock->now();
        $threeHoursInSeconds = 3*60*60;
        $this->clock->driftForwardBySeconds($threeHoursInSeconds);
        $from = $this->clock->now(); 
        $this->repository->archive($this->aJob($workable2)->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->archive($this->aJob($workable3)->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $this->aJobToSchedule($this->aJob($workable4)->beforeExecution($ed)->beforeExecution($ed))->inBackground()->execute();
        $this->aJobToSchedule($this->aJob($workable5)->beforeExecution($ed)->beforeExecution($ed))->inBackground()->execute();
        $this->aJobToSchedule($this->aJob($workable6))->inBackground()->execute();
        $jobs = $this->repository->recentJobsWithManyAttempts($from);
        $jobsFounds = 0;
        foreach ($jobs as $job) {
            $this->assertRegExp(
                '/many_attempts_and_archived|many_attempts_and_scheduled/',
                reset($job->export()['workable']['parameters'])
            );
            $jobsFounds++;
        }
        $this->assertEquals(4, $jobsFounds);
    }    

    public function testCleanOldArchived()
    {
        $ed = $this->eventDispatcher;
        $this->repository->archive($this->aJob()->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->archive($this->aJob()->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->archive($this->aJob()->beforeExecution($ed)->afterExecution(42, $ed));
        $this->assertEquals(3, $this->repository->cleanArchived(T\now()));
        $this->assertEquals(0, $this->repository->countArchived());
    }

    public function testCleaningOfOldArchivedCanBeLimitedByTime()
    {
        $ed = $this->eventDispatcher;
        $this->repository->archive($this->aJob()->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->archive($this->aJob()->beforeExecution($ed)->afterExecution(42, $ed));
        $time1 = $this->clock->now();
        $this->clock->driftForwardBySeconds(2 * 60 * 60);
        $this->repository->archive($this->aJob()->beforeExecution($ed)->afterExecution(42, $ed));
        $this->assertEquals(2, $this->repository->cleanArchived($time1));
        $this->assertEquals(1, $this->repository->countArchived());
    }

    private function aJob($workable = null)
    {
        if (is_null($workable)) {
            $workable = $this
                ->getMockBuilder('Recruiter\Workable')
                ->getMock();
        }

        return Job::around($workable, $this->repository)
            ->scheduleAt(T\now()->before(T\seconds(5)));
    }

    private function aJobToSchedule($job = null)
    {
        if (is_null($job)) {
            $job = $this->aJob();
        }

        return new JobToSchedule($job);
    }
}
