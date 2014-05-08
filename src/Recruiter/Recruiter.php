<?php

namespace Recruiter;

use MongoDB;

class Recruiter
{
    private $db;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->scheduledJobCollection = $db->selectCollection('scheduled');
        $this->archiveJobCollection = $db->selectCollection('archive');
        $this->rosterCollection = $db->selectCollection('roster');
    }

    public function meet(Worker $worker)
    {
        $worker->addTo($this->rosterCollection);
    }

    public function jobOf(Workable $doable)
    {
        return Job::around($doable, $this);
    }

    public function enqueue(Job $job)
    {
        if ($job->isActive()) {
            $this->schedule($job);
        } else {
            $this->archive($job);
        }
    }

    public function schedule(Job $job)
    {
        $this->scheduledJobCollection->save($job->export());
    }

    public function archive(Job $job)
    {
        $document = $job->export();
        $this->scheduledJobCollection->remove(array('_id' => $document['_id']));
        $this->archiveJobCollection->save($document);
    }
}
