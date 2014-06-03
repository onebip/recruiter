<?php

namespace Recruiter;

use MongoDB;
use MongoId;

use Timeless as T;
use Functional as _;

class Recruiter
{
    private $db;
    private $jobs;
    private $workers;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->jobs = new Job\Repository($db);
        $this->workers = new Worker\Repository($db, $this);
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

    public function assignJobsToWorkers()
    {
        $roster = $this->db->selectCollection('roster');
        $scheduled = $this->db->selectCollection('scheduled');
        $workersPerUnit = 42;

        return Worker::pickAvailableWorkers($roster, $workersPerUnit,
            function($worksOn, $workers) use ($scheduled, $roster)
        {
            return Job::pickReadyJobsForWorkers($scheduled, $worksOn, $workers,
                function($worksOn, $workers, $jobs) use($scheduled, $roster)
            {
                list($assignments, $jobs, $workers) = $this->combineJobsWithWorkers($jobs, $workers);

                Job::lockAll($scheduled, $jobs);

                // ASSIGN JOBS TO WORKERS
                $roster->update(
                    ['_id' => ['$in' => array_values($workers)]],
                    ['$set' => [
                        'available' => false,
                        'assigned_to' => array_combine(
                                _\map($workers, function($id) {return (string)$id;}),
                                $jobs
                        ),
                        'assigned_since' => T\MongoDate::now()
                    ]],
                    ['multiple' => true]
                );

                return $assignments;
            });
        });
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }

    private function combineJobsWithWorkers($jobs, $workers)
    {
        $assignments = min(count($workers), count($jobs));
        $workers = array_slice($workers, 0, $assignments);
        $jobs = array_slice($jobs, 0, $assignments);
        return [$assignments, $jobs, $workers];
    }
}
