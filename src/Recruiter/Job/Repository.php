<?php

namespace Recruiter\Job;

use MongoDB;
use MongoDate;
use MongoCollection;
use Recruiter\Recruiter;
use Recruiter\Job;

class Repository
{
    private $scheduled;
    private $archived;
    private $recruiter;

    public function __construct(MongoDB $db, Recruiter $recruiter)
    {
        $this->scheduled = $db->selectCollection('scheduled');
        $this->archived = $db->selectCollection('archived');
        $this->recruiter = $recruiter;
    }

    public function scheduled($id)
    {
        return array_shift(
            $this->map(
                $this->scheduled->find(['_id' => $id])
            )
        );
    }

    public function pickFor($worker)
    {
        return $this->map(
            $this->scheduled
                ->find([
                    'scheduled_at' => ['$lt' => new MongoDate()],
                    'active' => true,
                    'locked' => false
                ])
                ->sort(['scheduled_at' => 1])
                ->limit(1)
        );
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

    public function save(Job $job)
    {
        if ($job->isActive()) {
            $this->scheduled->save($job->export());
        }
    }

    public function refresh(Job $job)
    {
        $job->updateWith(
            $this->roster->findOne(['_id' => $job->id()])
        );
    }

    private function map($cursor)
    {
        $jobs = [];
        while ($cursor->hasNext()) {
            $jobs[] = Job::import($cursor->getNext(), $this->recruiter, $this);
        }
        return $jobs;
    }
}
