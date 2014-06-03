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

        return Worker::pickAvailableWorkers(
            $roster, $workersPerUnit, function($worksOn, $workers) use ($scheduled, $roster)
        {
            // PICK READY JOBS
            $jobsReadyToBeDone = _\pluck(
                $scheduled
                    ->find(
                        (Worker::canWorkOnAnyJobs($worksOn) ?
                            [   'scheduled_at' => ['$lt' => T\MongoDate::now()],
                                'active' => true,
                                'locked' => false,
                            ] :
                            [   'scheduled_at' => ['$lt' => T\MongoDate::now()],
                                'active' => true,
                                'locked' => false,
                                'tags' => $worksOn,
                            ]
                        ),
                        [   '_id' => 1
                        ]
                    )
                    ->sort(['scheduled_at' => 1])
                    ->limit(count($workers)),
                '_id'
            );
            if (count($jobsReadyToBeDone) === 0) {
                return 0;
            }

            // ASSIGNMENTS
            $numberOfAssignments = min(count($workers), count($jobsReadyToBeDone));
            $workers = array_slice($workers, 0, $numberOfAssignments);
            $jobsReadyToBeDone = array_slice($jobsReadyToBeDone, 0, $numberOfAssignments);

            // LOCK JOBS
            $scheduled->update(
                ['_id' => ['$in' => $jobsReadyToBeDone]],
                ['$set' => ['locked' => true]],
                ['multiple' => true]
            );

            // ASSIGN JOBS TO WORKERS
            $roster->update(
                ['_id' => ['$in' => array_values($workers)]],
                ['$set' => [
                    'available' => false,
                    'assigned_to' => array_combine(
                            _\map($workers, function($id) {return (string)$id;}),
                            $jobsReadyToBeDone
                    ),
                    'assigned_since' => T\MongoDate::now()
                ]],
                ['multiple' => true]
            );

            return $numberOfAssignments;
        });
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }
}
