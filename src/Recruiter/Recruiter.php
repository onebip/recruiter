<?php
namespace Recruiter;

use DateTime;
use MongoDB;
use Recruiter\Option\MemoryLimit;
use Timeless as T;
use Timeless\Interval;
use Timeless\Moment;
use Recruiter\Utils\Chainable;

use Onebip\Clock;
use Onebip\Concurrency\MongoLock;
use Onebip\Concurrency\LockNotAvailableException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use RuntimeException;

class Recruiter
{
    private $db;
    private $jobs;
    private $workers;
    private $lock;
    private $eventDispatcher;

    const POLL_FACTOR = 0.5;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->jobs = new Job\Repository($db);
        $this->workers = new Worker\Repository($db, $this);
        $this->lock = MongoLock::forProgram('RECRUITER', $db->selectCollection('metadata'));
        $this->eventDispatcher = new EventDispatcher();
    }

    public function hire(MemoryLimit $memoryLimit)
    {
        return Worker::workFor($this, $this->workers, $memoryLimit);
    }

    public function jobOf(Workable $workable)
    {
        return new JobToSchedule(
            Job::around($workable, $this->jobs)
        );
    }

    public function queued()
    {
        return $this->jobs->queued();
    }

    public function scheduled()
    {
        return $this->jobs->scheduledCount();
    }

    public function queuedGroupedBy($field, array $query = [], $group = null)
    {
        return $this->jobs->queuedGroupedBy($field, $query, $group);
    }

    public function statistics($group = null, Moment $at = null, array $query = [])
    {
        $totalsScheduledJobs = $this->jobs->scheduledCount($group, $query);
        $queued = $this->jobs->queued($group, $at, $at ? $at->before(T\hour(24)) : null, $query);
        $postponed = $this->jobs->postponed($group, $at, $query);

        return array_merge(
            [
                'jobs' => [
                    'queued' => $queued,
                    'postponed' => $postponed,
                    'zombies' => $totalsScheduledJobs - ($queued + $postponed),
                ],
            ],
            $this->jobs->recentHistory($group, $at, $query)
        );
    }

    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * @step
     * @return bool it worked
     */
    public function becomeMaster(Interval $leaseTime, callable $listener = null): bool
    {
        if (is_null($listener)) {
            $listener = function () {};
        }

        $tryToAcquireLock = function () use ($leaseTime, $listener) {
            $this->lock->acquire($leaseTime->seconds());
            $listener('lock.acquired', $leaseTime);
            return true;
        };

        $tryToRefreshLock = function () use ($leaseTime, $listener) {
            $this->lock->refresh($leaseTime->seconds());
            $listener('lock.refreshed', $leaseTime);
            return true;
        };

        $tryToWaitLockExpiration = function () use ($leaseTime, $listener) {
            $pollTime = $this->pollTimeOfLock($leaseTime);
            $listener('lock.notAvailable', new Interval($pollTime * 1000));
            $this->lock->wait($pollTime, $pollTime);
            return false;
        };

        return Chainable::avoidException(LockNotAvailableException::class)
            ->do($tryToAcquireLock)
            ->or($tryToRefreshLock)
            ->or($tryToWaitLockExpiration)
            ->or(function() {return false;})
            ->done()
        ;
    }

    /**
     * @step
     * @return integer  how many
     */
    public function rollbackLockedJobs()
    {
        $assignedJobs = Worker::assignedJobs($this->db->selectCollection('roster'));
        return Job::rollbackLockedNotIn($this->db->selectCollection('scheduled'), $assignedJobs);
    }

    /**
     * @step
     */
    public function bye()
    {
        $this->lock->release();
    }

    public function assignJobsToWorkers()
    {
        return $this->assignLockedJobsToWorkers($this->bookJobsForWorkers());
    }

    /**
     * @step
     */
    public function bookJobsForWorkers()
    {
        $roster = $this->db->selectCollection('roster');
        $scheduled = $this->db->selectCollection('scheduled');
        $workersPerUnit = 42;

        $bookedJobs = [];
        foreach (Worker::pickAvailableWorkers($roster, $workersPerUnit) as $resultRow) {
            list ($worksOn, $workers) = $resultRow;

            $result = Job::pickReadyJobsForWorkers($scheduled, $worksOn, $workers);
            if ($result) {
                list($worksOn, $workers, $jobs) = $result;
                list($assignments, $jobs, $workers) = $this->combineJobsWithWorkers($jobs, $workers);

                Job::lockAll($scheduled, $jobs);
                $bookedJobs[] = [$jobs, $workers];
            }
        }
        return $bookedJobs;
    }

    /**
     * @step
     */
    public function assignLockedJobsToWorkers($bookedJobs)
    {
        $assignments = [];
        $totalActualAssignments = 0;
        $roster = $this->db->selectCollection('roster');
        foreach ($bookedJobs as $row) {
            list ($jobs, $workers, ) = $row;
            list ($newAssignments, $actualAssignmentsNumber) = Worker::tryToAssignJobsToWorkers($roster, $jobs, $workers);
            if (array_intersect_key($assignments, $newAssignments)) {
                throw new RuntimeException("Conflicting assignments: current were " . var_export($assignments, true) . " and we want to also assign " . var_export($newAssignments, true));
            }
            $assignments = array_merge(
                $assignments, $newAssignments
            );
            $totalActualAssignments += $actualAssignmentsNumber;
        }
        return [
            array_map(function($value) { return (string) $value; }, $assignments),
            $totalActualAssignments
        ];
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }

    /**
     * @step
     * @return integer  how many jobs were unlocked as a result
     */
    public function retireDeadWorkers(Clock $clock, Interval $consideredDeadAfter)
    {
        return $this->jobs->releaseAll(
            $jobsAssignedToDeadWorkers = Worker::retireDeadWorkers($this->workers, $clock, $consideredDeadAfter)
        );
    }

    public function createCollectionsAndIndexes()
    {
        $this->db->command(['collMod' => 'scheduled', 'usePowerOf2Sizes' => true]);
        $this->db->selectCollection('scheduled')->ensureIndex(
            [
                'group' => 1,
                'locked' => 1,
                'scheduled_at' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('scheduled')->ensureIndex(
            [
                'locked' => 1,
                'scheduled_at' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('scheduled')->ensureIndex(
            [
                'locked' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('scheduled')->ensureIndex(
            [
                'tags' => 1,
            ],
            ['background' => true, 'sparse' => true]
        );

        $this->db->command(['collMod' => 'archived', 'usePowerOf2Sizes' => true]);
        $this->db->selectCollection('archived')->ensureIndex(
            [
                'created_at' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('archived')->ensureIndex(
            [
                'created_at' => 1,
                'group' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('archived')->ensureIndex(
            [
                'last_execution.ended_at' => 1,
            ],
            ['background' => true]
        );

        $this->db->command(['collMod' => 'roster', 'usePowerOf2Sizes' => true]);
        $this->db->selectCollection('roster')->ensureIndex(
            [
                'available' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('roster')->ensureIndex(
            [
                'last_seen_at' => 1,
            ],
            ['background' => true]
        );
    }

    private function combineJobsWithWorkers($jobs, $workers)
    {
        $assignments = min(count($workers), count($jobs));
        $workers = array_slice($workers, 0, $assignments);
        $jobs = array_slice($jobs, 0, $assignments);
        return [$assignments, $jobs, $workers];
    }

    /**
     * @return integer  seconds
     */
    private function pollTimeOfLock(Interval $leaseTime)
    {
        return round($leaseTime->seconds() * self::POLL_FACTOR);
    }
}
