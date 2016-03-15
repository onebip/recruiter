<?php

namespace Recruiter\Job;

use MongoDB;
use MongoCollection;
use Recruiter\Recruiter;
use Recruiter\Job;
use Timeless as T;
use RuntimeException;

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

    public function recentHistory()
    {
        $lastMinute = [
            'last_execution.ended_at' => [
                '$gt' => T\MongoDate::from(T\now()->before(T\minute(1))),
                '$lte' => T\MongoDate::from(T\now())
            ]
        ];
        $document = $this->archived->aggregate([
            ['$match' => $lastMinute],
            ['$project' => [
                'latency' => ['$subtract' => [
                    '$last_execution.started_at',
                    '$last_execution.scheduled_at',
                ]],
                'execution_time' => ['$subtract' => [
                    '$last_execution.ended_at',
                    '$last_execution.started_at',
                ]],
            ]],
            ['$group' => [
                '_id' => 1, 
                'throughput' => ['$sum' => 1],
                'latency' => ['$avg' => '$latency'],
                'execution_time' => ['$avg' => '$execution_time'],
            ]],
        ]);
        if (!$document['ok']) {
            throw new RuntimeException();
        }
        if (count($document['result']) !== 1) {
            throw new RuntimeException();
        }
        $throughputPerMinute = $document['result'][0]['throughput'];
        $averageLatency = $document['result'][0]['latency'] / 1000;
        return [
            'throughput' => [
                'value' => $throughputPerMinute,
                'value_per_second' => $throughputPerMinute/60.0,
            ],
            'latency' => [
                'average' => $averageLatency,
            ],
            'execution_time' => [
                'average' => $averageExecutionTime,
            ],
        ];        
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
