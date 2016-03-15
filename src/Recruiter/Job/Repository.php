<?php

namespace Recruiter\Job;

use MongoDB;
use MongoCollection;
use Recruiter\Recruiter;
use Recruiter\Job;
use Timeless as T;

class Repository
{
    private $scheduled;
    private $archived;

    public function __construct(MongoDB $db)
    {
        $this->scheduled = $db->selectCollection('scheduled');
        $this->archived = $db->selectCollection('archived');
    }

    public function all()
    {
        return $this->map(
            $this->scheduled->find()
        );
    }

    public function archiveAll()
    {
        foreach ($this->all() as $job) {
            $this->archive($job);
        }
    }

    public function scheduled($id)
    {
        $found = $this->map($this->scheduled->find(['_id' => $id]));
        if (count($found) === 0) {
            throw new \Exception("Unable to find scheduled job with ObjectId('{$id}')");
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

    public function releaseAll($jobIds)
    {
        return $this->scheduled->update(
            ['_id' => ['$in' => $jobIds]],
            ['$set' => ['active' => true, 'locked' => false]],
            ['multiple' => true]
        )['n'];
    }

    /**
     * @param int
     */
    public function countArchived()
    {
        return $this->archived->count();
    }

    public function queued($tag = null)
    {
        $query = [
            'scheduled_at' => ['$lte' => T\MongoDate::now()],
        ];
        if ($tag !== null) {
            $query['tags'] = $tag;
        }
        return $this->scheduled->count($query);
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
