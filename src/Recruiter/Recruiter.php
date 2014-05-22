<?php

namespace Recruiter;

use MongoDB;
use MongoDate;

class Recruiter
{
    private $db;
    private $jobs;
    private $workers;
    private $contracts;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->jobs = new Job\Repository($db, $this);
        $this->workers = new Worker\Repository($db, $this);
        $this->contracts = [];
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
        // TODO: replace with Workers\Alive
        $roster = $this->db->selectCollection('roster');
        return Workers\AvailableToWork::from(
            $roster->find(['available' => true], ['_id' => true, 'skills' => true]),
            $roster
        );
    }

    public function jobsReadyToBeDone()
    {
        return new Jobs\Scheduled($this->db->selectCollection('scheduled'));
    }

    public function signContractOf($contract)
    {
        $this->db->selectCollection('contracts')->save($contract);
    }

    public function assignedJobTo($contractId, $workerId)
    {
        if (!array_key_exists((string)$contractId, $this->contracts)) {
            $this->contracts[(string)$contractId] =
                $this->db->selectCollection('contracts')->findOne(['_id' => $contractId]);
        }
        return $this->scheduledJob(
            $this->contracts[(string)$contractId]['assignments'][(string)$workerId]
        );
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }

    /* public function pickJobFor($worker) */
    /* { */
    /*     return $this->jobs->pickFor($worker); */
    /* } */
}
