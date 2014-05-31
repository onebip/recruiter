<?php

namespace Recruiter;

use MongoDB;
use MongoId;
use MongoDate;

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

        // PICK AVAILABLE WORKERS
        $workersAvailableToWork = _\pluck(
            $roster->find(['available' => true], ['_id' => 1]), '_id'
        );
        if (count($workersAvailableToWork) === 0) {
            return 0;
        }
        // TODO: replace magic number
        $workersAvailableToWork = array_slice(
            $workersAvailableToWork, 0, min(count($workersAvailableToWork), 42)
        );

        // PICK READY JOBS
        $jobsReadyToBeDone = _\pluck(
            $scheduled
                ->find(
                    [   'scheduled_at' => ['$lt' => new MongoDate()],
                        'active' => true,
                        'locked' => false
                    ],
                    [   '_id' => 1
                    ]
                )
                ->sort(['scheduled_at' => 1])
                ->limit(count($workersAvailableToWork)),
            '_id'
        );
        if (count($jobsReadyToBeDone) === 0) {
            return 0;
        }

        // ASSIGNMENTS
        $numberOfAssignments = min(count($workersAvailableToWork), count($jobsReadyToBeDone));
        $workersAvailableToWork = array_slice($workersAvailableToWork, 0, $numberOfAssignments);
        $jobsReadyToBeDone = array_slice($jobsReadyToBeDone, 0, $numberOfAssignments);

        // LOCK JOBS
        $scheduled->update(
            ['_id' => ['$in' => $jobsReadyToBeDone]],
            ['$set' => ['locked' => true]],
            ['multiple' => true]
        );

        // ASSIGN JOBS TO WORKERS
        $roster->update(
            ['_id' => ['$in' => array_values($workersAvailableToWork)]],
            ['$set' => [
                'available' => false,
                'assigned_to' => array_combine(
                        _\map($workersAvailableToWork, function($id) {return (string)$id;}),
                        $jobsReadyToBeDone
                ),
                'assigned_since' => new MongoDate()
            ]],
            ['multiple' => true]
        );

        return $numberOfAssignments;
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }
}
