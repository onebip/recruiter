<?php

namespace Recruiter;

use MongoDB;
use Timeless\Interval;

use Onebip\Clock;
use Onebip\Concurrency\MongoLock;
use Onebip\Concurrency\LockNotAvailableException;

class Recruiter
{
    private $db;
    private $jobs;
    private $workers;
    private $lock;

    const WAIT_FACTOR = 3;
    const LOCK_FACTOR = 1.5;
    const POLL_TIME = 5;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->jobs = new Job\Repository($db);
        $this->workers = new Worker\Repository($db, $this);
        $this->lock = MongoLock::forProgram('RECRUITER', $db->selectCollection('metadata'));
    }

    public function hire()
    {
        return Worker::workFor($this, $this->workers);
    }

    public function jobOf(Workable $workable)
    {
        return new JobToSchedule(
            Job::around($workable, $this->jobs)
        );
    }

    public function ensureIsTheOnlyOne(Interval $timeToWaitAtMost, $otherwise)
    {
        try {
            $this->lock->wait(self::POLL_TIME, $timeToWaitAtMost->seconds() * self::WAIT_FACTOR);
            $this->lock->acquire($timeToWaitAtMost->seconds() * self::LOCK_FACTOR);
        } catch(LockNotAvailableException $e) {
            $otherwise();
        }
    }

    public function init()
    {
        $this->rollbackLockedJobs();
    }

    /**
     * @step
     */
    public function rollbackLockedJobs()
    {
        $assignedJobs = Worker::assignedJobs($this->db->selectCollection('roster'));
        Job::rollbackLockedNotIn($this->db->selectCollection('scheduled'), $assignedJobs);
    }

    /**
     * @step
     */
    public function stillHere(Interval $timeToWaitAtMost)
    {
        $this->lock->refresh($timeToWaitAtMost->seconds() * self::LOCK_FACTOR);
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
        $bookedJobs = $this->bookJobsForWorkers();

        return $this->assignLockedJobsToWorkers($bookedJobs);
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
                $bookedJobs[] = [$jobs, $workers, $assignments];
            }
        }
        return $bookedJobs;
    }

    /**
     * @step
     */
    public function assignLockedJobsToWorkers($bookedJobs)
    {
        $numberOfWorkersWithJobs = 0;
        $roster = $this->db->selectCollection('roster');
        foreach ($bookedJobs as $row) {
            list ($jobs, $workers, $assignments) = $row;
            Worker::assignJobsToWorkers($roster, $jobs, $workers);
            $numberOfWorkersWithJobs += $assignments;
        }
        return $numberOfWorkersWithJobs;
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }

    /**
     * @step
     */
    public function retireDeadWorkers(Clock $clock)
    {
        $this->jobs->releaseAll(
            $jobsAssignedToDeadWorkers = Worker::retireDeadWorkers($this->workers, $clock)
        );
    }

    public function createCollectionsAndIndexes()
    {
        $this->db->command(['collMod' => 'scheduled', 'usePowerOf2Sizes' => true]);
        $this->db->selectCollection('scheduled')->ensureIndex([
            'scheduled_at' => 1,
            'active' => 1,
            'locked' => 1,
            'tags' => 1,
        ]);

        $this->db->command(['collMod' => 'archived', 'usePowerOf2Sizes' => true]);
        $this->db->selectCollection('archived')->ensureIndex([
            'created_at' => 1,
        ]);

        $this->db->command(['collMod' => 'roster', 'usePowerOf2Sizes' => true]);
        $this->db->selectCollection('roster')->ensureIndex([
            'available' => 1,
        ]);
        $this->db->selectCollection('roster')->ensureIndex([
            'last_seen_at' => 1,
        ]);
    }

    private function combineJobsWithWorkers($jobs, $workers)
    {
        $assignments = min(count($workers), count($jobs));
        $workers = array_slice($workers, 0, $assignments);
        $jobs = array_slice($jobs, 0, $assignments);
        return [$assignments, $jobs, $workers];
    }
}
