<?php
namespace Recruiter;

use MongoDB;
use Onebip\Clock;
use Onebip\Concurrency\LockNotAvailableException;
use Onebip\Concurrency\MongoLock;
use Recruiter\Infrastructure\Memory\MemoryLimit;
use Recruiter\Utils\Chainable;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Timeless as T;
use Timeless\Interval;
use Timeless\Moment;

class Recruiter
{
    private $db;
    private $jobs;
    private $workers;
    private $eventDispatcher;

    public function __construct(MongoDB\Database $db)
    {
        $this->db = $db;
        $this->jobs = new Job\Repository($db);
        $this->workers = new Worker\Repository($db, $this);
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
                $assignments,
                $newAssignments
            );
            $totalActualAssignments += $actualAssignmentsNumber;
        }

        return [
            array_map(function ($value) {
                return (string) $value;
            }, $assignments),
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

    public function flushJobsSynchronously(): SynchronousExecutionReport
    {
        $report = [];

        foreach ($this->jobs->all() as $job) {
            $report[(string) $job->id()] = $job->execute($this->eventDispatcher);
        }

        return SynchronousExecutionReport::fromArray($report);
    }

    public function createCollectionsAndIndexes()
    {
        $this->db->selectCollection('scheduled')->createIndex(
            [
                'group' => 1,
                'locked' => 1,
                'scheduled_at' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('scheduled')->createIndex(
            [
                'locked' => 1,
                'scheduled_at' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('scheduled')->createIndex(
            [
                'locked' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('scheduled')->createIndex(
            [
                'tags' => 1,
            ],
            ['background' => true, 'sparse' => true]
        );

        $this->db->selectCollection('archived')->createIndex(
            [
                'created_at' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('archived')->createIndex(
            [
                'created_at' => 1,
                'group' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('archived')->createIndex(
            [
                'last_execution.ended_at' => 1,
            ],
            ['background' => true]
        );

        $this->db->selectCollection('roster')->createIndex(
            [
                'available' => 1,
            ],
            ['background' => true]
        );
        $this->db->selectCollection('roster')->createIndex(
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
}
