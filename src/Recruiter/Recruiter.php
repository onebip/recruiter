<?php

namespace Recruiter;

use MongoDB;
use MongoDate;

class Recruiter
{
    private $db;
    private $jobs;
    private $workers;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->jobs = new Job\Repository($db, $this);
        $this->workers = new Worker\Repository($db, $this);
    }

    public function hire()
    {
        return Worker::workFor($this, $this->workers);
    }

    public function jobOf(Workable $workable)
    {
        return Job::around($workable, $this, $this->jobs);
    }

    public function workersAvailableToWork()
    {
        return $this->workers->available();
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }

    public function pickJobFor($worker)
    {
        return $this->jobs->pickFor($worker);
    }

    public function assignJobTo($job, $worker)
    {
        $job->assignTo($worker);
        $worker->assignedTo($job); // TODO: move to Job::assignTo
    }
}
