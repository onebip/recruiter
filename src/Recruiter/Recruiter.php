<?php

namespace Recruiter;

use MongoDB;

class Recruiter
{
    private $db;
    private $scheduled;
    private $archived;
    private $roster;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->scheduled = $db->selectCollection('scheduled');
        $this->archived = $db->selectCollection('archived');
        $this->roster = $db->selectCollection('roster');
    }

    public function hire()
    {
        return Worker::workFor($this, $this->db);
    }

    public function jobOf(Workable $doable)
    {
        return Job::around($doable, $this);
    }

    public function accept(Job $job)
    {
        if ($job->isActive()) {
            $this->schedule($job);
        } else {
            $this->archive($job);
        }
    }

    public function schedule(Job $job)
    {
        $this->scheduled->save($job->export());
    }

    public function archive(Job $job)
    {
        $document = $job->export();
        $this->scheduled->remove(array('_id' => $document['_id']));
        $this->archived->save($document);
    }
}
