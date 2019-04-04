<?php
namespace Recruiter\Job;

use DateTime;
use MongoDB\BSON\ObjectId;
use PHPUnit\Framework\TestCase;
use Recruiter\Factory;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;
use Recruiter\Job;
use Recruiter\JobToSchedule;
use Recruiter\RetryPolicy\ExponentialBackoff;
use Timeless as T;
use Timeless\Interval;
use Timeless\Moment;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RepositoryTest extends TestCase
{
    public function setUp(): void
    {
        $factory = new Factory();
        $this->recruiterDb = $factory->getMongoDb(MongoURI::from('mongodb://localhost:27017/recruiter'), []);
        $this->recruiterDb->drop();
        $this->repository = new Repository($this->recruiterDb);
        $this->clock = T\clock()->stop();
        $this->eventDispatcher = $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
    }

    public function tearDown(): void
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

    public function testCountQueuedJobsGroupingByASpecificKeyword()
    {
        $workable1 = $this->workableMock();
        $workable2 = $this->workableMock();

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

    public function testGetDelayedScheduledJobs()
    {
        $workable1 = $this->workableMockWithCustomParameters([
            'job1' => 'delayed_and_unpicked'
        ]);
        $workable2 = $this->workableMockWithCustomParameters([
            'job2' => 'delayed_and_unpicked'
        ]);
        $workable3 = $this->workableMockWithCustomParameters([
            'job3' => 'in_schedulation'
        ]);
        $this->aJobToSchedule($this->aJob($workable1))->inBackground()->execute();
        $this->aJobToSchedule($this->aJob($workable2))->inBackground()->execute();
        $lowerLimit = $this->clock->now();
        $fiveHoursInSeconds = 5*60*60;
        $this->clock->driftForwardBySeconds($fiveHoursInSeconds);
        $this->aJobToSchedule($this->aJob($workable3))->inBackground()->execute();
        $jobs = $this->repository->delayedScheduledJobs($lowerLimit);
        $jobsFounds = 0;
        foreach ($jobs as $job) {
            $this->assertEquals('delayed_and_unpicked', reset($job->export()['workable']['parameters']));
            $jobsFounds++;
        }
        $this->assertEquals(2, $jobsFounds);
    }

    public function testCountDelayedScheduledJobs()
    {
        $this->aJobToSchedule($this->aJob())->inBackground()->execute();
        $this->aJobToSchedule($this->aJob())->inBackground()->execute();
        $lowerLimit = $this->clock->now();
        $twoHoursInSeconds = 2*60*60;
        $this->clock->driftForwardBySeconds($twoHoursInSeconds);
        $this->aJobToSchedule($this->aJob())->inBackground()->execute();
        $this->assertEquals(2, $this->repository->countDelayedScheduledJobs($lowerLimit));
    }

    public function testCountRecentJobsWithManyAttempts()
    {
        $ed = $this->eventDispatcher;
        $this->repository->archive($this->aJob()->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $this->clock->now();
        $threeHoursInSeconds = 3*60*60;
        $this->clock->driftForwardBySeconds($threeHoursInSeconds);
        $lowerLimit = $this->clock->now();
        $this->repository->archive($this->aJob()->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->archive($this->aJob()->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $createdAt = $endedAt = $this->clock->now();
        $this->repository->save($this->jobMockWithAttemptsAndCustomParameters($createdAt, $endedAt));
        $this->repository->save($this->jobMockWithAttemptsAndCustomParameters($createdAt, $endedAt));
        $this->aJobToSchedule($this->aJob())->inBackground()->execute();
        $upperLimit = $this->clock->now();
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $createdAt = $endedAt = $this->clock->now();
        $this->repository->archive($this->aJob()->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->save($this->jobMockWithAttemptsAndCustomParameters($createdAt, $endedAt));
        $this->assertEquals(4, $this->repository->countRecentJobsWithManyAttempts($lowerLimit, $upperLimit));
    }

    public function testGetRecentJobsWithManyAttempts()
    {
        $ed = $this->eventDispatcher;
        $workable1 = $this->workableMockWithCustomParameters([
            'job1' => 'many_attempts_and_archived_but_too_old'
        ]);
        $workable2 = $this->workableMockWithCustomParameters([
            'job2' => 'many_attempts_and_archived'
        ]);
        $workable3 = $this->workableMockWithCustomParameters([
            'job3' => 'many_attempts_and_archived'
        ]);
        $workable4  = [
            'job4' => 'many_attempts_and_scheduled'
        ];
        $workable5  = [
            'job5' => 'many_attempts_and_scheduled'
        ];
        $workable6 = $this->workableMockWithCustomParameters([
            'job6' => 'one_attempt_and_scheduled'
        ]);
        $this->repository->archive($this->aJob($workable1)->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $this->clock->now();
        $threeHoursInSeconds = 3*60*60;
        $this->clock->driftForwardBySeconds($threeHoursInSeconds);
        $lowerLimit = $this->clock->now();
        $this->repository->archive($this->aJob($workable2)->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $this->repository->archive($this->aJob($workable3)->beforeExecution($ed)->beforeExecution($ed)->afterExecution(42, $ed));
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $createdAt = $endedAt = $this->clock->now();
        $this->repository->save($this->jobMockWithAttemptsAndCustomParameters($createdAt, $endedAt, $workable4));
        $this->repository->save($this->jobMockWithAttemptsAndCustomParameters($createdAt, $endedAt, $workable5));
        $upperLimit = $this->clock->now();
        $this->aJobToSchedule($this->aJob($workable6))->inBackground()->execute();
        $jobs = $this->repository->recentJobsWithManyAttempts($lowerLimit, $upperLimit);
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

    public function testCountSlowRecentJobs()
    {
        $ed = $this->eventDispatcher;
        $elapseTimeInSecondsBeforeJobsExecutionEnd = 6;
        $createdAt = $endedAt = $this->clock->now();
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt->after(Interval::parse($elapseTimeInSecondsBeforeJobsExecutionEnd . ' s'))
            )
        );
        $archivedJobSlowExpired = $this->aJob()->beforeExecution($ed);
        $this->clock->driftForwardBySeconds($elapseTimeInSecondsBeforeJobsExecutionEnd);
        $archivedJobSlowExpired->afterExecution(42, $ed);
        $threeHoursInSeconds = 3*60*60;
        $this->clock->driftForwardBySeconds($threeHoursInSeconds);
        $lowerLimit = $createdAt = $endedAt = $this->clock->now();
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt->after(Interval::parse($elapseTimeInSecondsBeforeJobsExecutionEnd . ' s'))
            )
        );
        $archivedJobSlow1 = $this->aJob()->beforeExecution($ed);
        $this->clock->driftForwardBySeconds($elapseTimeInSecondsBeforeJobsExecutionEnd);
        $archivedJobSlow1->afterExecution(42, $ed);
        $this->repository->archive($archivedJobSlow1);
        $archivedJobSlow2 = $this->aJob()->beforeExecution($ed);
        $this->clock->driftForwardBySeconds($elapseTimeInSecondsBeforeJobsExecutionEnd);
        $archivedJobSlow2->afterExecution(42, $ed);
        $this->repository->archive($archivedJobSlow2);
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $createdAt = $endedAt = $this->clock->now();
        $archivedJobNotSlow = $this->aJob()->beforeExecution($ed)->afterExecution(42, $ed);
        $this->repository->archive($archivedJobNotSlow);
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt->after(Interval::parse($elapseTimeInSecondsBeforeJobsExecutionEnd . ' s'))
            )
        );
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $upperLimit = $createdAt = $endedAt = $this->clock->now();
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt
            )
        );
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $createdAt = $endedAt = $this->clock->now();
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt->after(Interval::parse($elapseTimeInSecondsBeforeJobsExecutionEnd . ' s'))
            )
        );
        $this->assertEquals(4, $this->repository->countSlowRecentJobs($lowerLimit, $upperLimit));
    }

    public function testGetSlowRecentJobs()
    {
        $ed = $this->eventDispatcher;
        $elapseTimeInSecondsBeforeJobsExecutionEnd = 6;
        $createdAt = $endedAt = $this->clock->now();
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt->after(Interval::parse($elapseTimeInSecondsBeforeJobsExecutionEnd . ' s')),
                ['job_scheduled_old' => 'slow_jobs_scheduled_but_too_old']
            )
        );
        $archivedJobSlowExpired = $this->aJob($this->workableMockWithCustomParameters([
                'job_archived_old' => 'slow_job_archived_but_too_old'
            ]))->beforeExecution($ed);
        $this->clock->driftForwardBySeconds($elapseTimeInSecondsBeforeJobsExecutionEnd);
        $archivedJobSlowExpired->afterExecution(42, $ed);
        $threeHoursInSeconds = 3*60*60;
        $this->clock->driftForwardBySeconds($threeHoursInSeconds);
        $lowerLimit = $createdAt = $endedAt = $this->clock->now();
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt->after(Interval::parse($elapseTimeInSecondsBeforeJobsExecutionEnd . ' s')),
                ['job1_scheduled' => 'slow_job_recent_scheduled']
            )
        );
        $archivedJobSlow1 = $this->aJob($this->workableMockWithCustomParameters([
                'job1_archived' => 'slow_job_recent_archived'
            ]))->beforeExecution($ed);
        $archivedJobSlow2 = $this->aJob($this->workableMockWithCustomParameters([
                'job2_archived' => 'slow_job_recent_archived'
            ]))->beforeExecution($ed);
        $this->clock->driftForwardBySeconds($elapseTimeInSecondsBeforeJobsExecutionEnd);
        $archivedJobSlow1->afterExecution(41, $ed);
        $this->repository->archive($archivedJobSlow1);
        $archivedJobSlow2->afterExecution(42, $ed);
        $this->repository->archive($archivedJobSlow2);
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $createdAt = $endedAt = $this->clock->now();
        $archivedJobNotSlow = $this->aJob($this->workableMockWithCustomParameters([
                'job_archived' => 'job_archived_not_slow'
            ]))->beforeExecution($ed)->afterExecution(42, $ed);
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt->after(Interval::parse($elapseTimeInSecondsBeforeJobsExecutionEnd . ' s')),
                ['job2_scheduled' => 'slow_job_recent_scheduled']
            )
        );
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $upperLimit = $createdAt = $endedAt = $this->clock->now();
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt,
                ['job_scheduled' => 'job_recent_scheduled_slow']
            )
        );
        $oneHourInSeconds = 60*60;
        $this->clock->driftForwardBySeconds($oneHourInSeconds);
        $createdAt = $endedAt = $this->clock->now();
        $this->repository->save(
            $this->jobMockWithAttemptsAndCustomParameters(
                $createdAt,
                $endedAt->after(Interval::parse($elapseTimeInSecondsBeforeJobsExecutionEnd . ' s')),
                ['job3_scheduled' => 'slow_job_recent_scheduled']
            )
        );
        $jobs = $this->repository->slowRecentJobs($lowerLimit, $upperLimit);
        $jobsFounds = 0;
        foreach ($jobs as $job) {
            $this->assertRegExp(
                '/slow_job_recent_archived|slow_job_recent_scheduled/',
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
            $workable = $this->workableMock();
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

    private function workableMock()
    {
        return $this
            ->getMockBuilder('Recruiter\Workable')
            ->getMock();
    }

    private function workableMockWithCustomParameters($parameters)
    {
        $workable = $this->workableMock();
        $workable
            ->expects($this->any())
            ->method('export')
            ->will($this->returnValue($parameters));
        return $workable;
    }

    private function jobExecutionMock($executionParameters)
    {
        $jobExecutionMock = $this
            ->getMockBuilder('Recruiter\JobExecution')
            ->getMock();
        $jobExecutionMock->expects($this->once())
            ->method('export')
            ->will($this->returnValue($executionParameters));

        return $jobExecutionMock;
    }

    private function jobMockWithAttemptsAndCustomParameters(
        Moment $createdAt = null,
        Moment $endedAt = null,
        array $workableParameters = null
    ) {
        $parameters = [
            '_id' => new ObjectId(),
            'created_at' => T\MongoDate::from($createdAt),
            "done" => false,
            "attempts" => 10,
            "group" => "generic",
            "scheduled_at" => T\MongoDate::from($createdAt),
            "last_execution" => [
                "started_at" => T\MongoDate::from($createdAt),
                "ended_at" => T\MongoDate::from($endedAt)
            ],
            "retry_policy" => [
                "class" => "Recruiter\\RetryPolicy\\DoNotDoItAgain",
                "parameters" => []
            ]
        ];

        if (!empty($workableParameters)) {
            $parameters['workable']['class'] = 'Fake_Workable';
            $parameters['workable']['method'] = 'execute';
            $parameters['workable']['parameters'] = $workableParameters;
        }
        $job = $this
                ->getMockBuilder('Recruiter\Job')
                ->disableOriginalConstructor()
                ->getMock();
        $job->expects($this->once())
            ->method('export')
            ->will($this->returnValue($parameters));
        return $job;
    }
}
