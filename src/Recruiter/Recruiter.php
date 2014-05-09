<?php

namespace Recruiter;

use MongoDB;
use MongoDate;

class Recruiter
{
    private $db;
    private $scheduled;
    private $archived;
    private $workers;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->scheduled = $db->selectCollection('scheduled');
        $this->archived = $db->selectCollection('archived');
        $this->workers = new Worker\Repository($db, $this);
    }

    public function hire()
    {
        return Worker::workFor($this, $this->workers);
    }

    public function jobOf(Workable $doable)
    {
        return Job::around($doable, $this);
    }

    public function workersAvailableToWork()
    {
        return $this->workers->available();
    }

    public function job($id)
    {
        return Job::import(
            $this->scheduled->findOne(['_id' => $id]), $this
        );
    }

    public function pickJobFor($woker)
    {
        return $this->scheduled
            ->find([
                'scheduled_at' => ['$lt' => new MongoDate()],
                'active' => true,
                'locked' => false
            ])
            ->sort(['scheduled_at' => 1])
            ->limit(1);
    }

    public function assignJobTo($job, $worker)
    {
        $this->scheduled->update(
            ['_id' => $job['_id']],
            ['$set' => ['locked' => true]]
        );
        $worker->assignedTo($job);
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
