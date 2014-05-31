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

    public function __construct(MongoDB $db)
    {
        $this->scheduled = $db->selectCollection('scheduled');
        $this->archived = $db->selectCollection('archived');
    }

    public function scheduled($id)
    {
        $found = $this->map($this->scheduled->find(['_id' => $id]));
        if (count($found) === 0) {
            throw new Exception("Unable to find scheduled job with ObjectId('{$id}')");
        }
        return $found[0];
    }

    public function save(Job $job)
    {
        $this->scheduled->save($job->export());
    }

    public function archive(Job $job)
    {
        $document = $job->export();
        $this->scheduled->remove(array('_id' => $document['_id']));
        $this->archived->save($document);
    }

    private function map($cursor)
    {
        $jobs = [];
        while ($cursor->hasNext()) {
            $jobs[] = Job::import($cursor->getNext(), $this);
        }
        return $jobs;
    }
}
