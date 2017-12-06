<?php
namespace Recruiter\Job;

use Exception;
use MongoCollection;
use MongoDB;
use Recruiter\Job;
use Recruiter\Recruiter;
use RuntimeException;
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
            throw new Exception("Unable to find scheduled job with ObjectId('{$id}')");
        }
        return $found[0];
    }

    public function save(Job $job)
    {
        $document = $job->export();
        $this->scheduled->save($document);
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
            ['$set' => ['locked' => false, 'last_execution.crashed' => true]],
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

    public function cleanArchived(T\Moment $upperLimit)
    {
        $documents = $this->archived->find(
            [
                'last_execution.ended_at' => [
                    '$lte' => T\MongoDate::from($upperLimit),
                ]
            ],
            ['_id' => 1]
        );

        $deleted = 0;
        foreach ($documents as $document) {
            $this->archived->remove(['_id' => $document['_id']]);
            $deleted++;
        }

        return $deleted;
    }

    public function cleanScheduled(T\Moment $upperLimit)
    {
        $result = $this->scheduled->remove([
                'created_at' => [
                    '$lte' => T\MongoDate::from($upperLimit),
                ]
            ]
        );
        return $result['ok'] ? $result['n'] : 0;
    }

    public function queued(
        $group = null,
        T\Moment $at = null,
        T\Moment $from = null,
        array $query = []
    )
    {
        if ($at === null) {
            $at = T\now();
        }

        $query['scheduled_at']['$lte'] = T\MongoDate::from($at);

        if ($from !== null) {
            $query['scheduled_at']['$gt'] = T\MongoDate::from($from);
        }

        if ($group !== null) {
            $query['group'] = $group;
        }

        return $this->scheduled->count($query);
    }

    public function postponed($group = null, T\Moment $at = null, array $query = [])
    {
        if ($at === null) {
            $at = T\now();
        }

        $query['scheduled_at']['$gt'] = T\MongoDate::from($at);

        if ($group !== null) {
            $query['group'] = $group;
        }

        return $this->scheduled->count($query);
    }

    public function scheduledCount($group = null, array $query = [])
    {
        if ($group !== null) {
            $query['group'] = $group;
        }

        return $this->scheduled->count($query);
    }

    public function queuedGroupedBy($field, array $query = [], $group = null)
    {
        $query['scheduled_at']['$lte'] = T\MongoDate::from(T\now());
        if ($group !== null) {
            $query['group'] = $group;
        }

        $document = $this->scheduled->aggregate($pipeline = [
            ['$match' => $query],
            ['$group' => [
                '_id' => '$' . $field,
                'count' => ['$sum' => 1],
            ]],
        ]);

        if (!$document['ok']) {
            throw new RuntimeException("Pipeline failed: " . var_export($pipeline, true));
        }

        $distinctAndCount = [];
        foreach ($document['result'] as $r) {
            $distinctAndCount[$r['_id']] = $r['count'];
        }

        return $distinctAndCount;
    }

    public function recentHistory($group = null, T\Moment $at = null, array $query = [])
    {
        if ($at === null) {
            $at = T\now();
        }
        $lastMinute = array_merge(
            $query,
            [
                'last_execution.ended_at' => [
                    '$gt' => T\MongoDate::from($at->before(T\minute(1))),
                    '$lte' => T\MongoDate::from($at)
                ],
            ]
        );
        if ($group !== null) {
            $lastMinute['group'] = $group;
        }
        $document = $this->archived->aggregate($pipeline = [
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
            throw new RuntimeException("Pipeline failed: " . var_export($pipeline, true));
        }
        if (count($document['result']) === 0) {
            $throughputPerMinute = 0.0;
            $averageLatency = 0.0;
            $averageExecutionTime = 0;
        } else if (count($document['result']) === 1) {
            $throughputPerMinute = (float) $document['result'][0]['throughput'];
            $averageLatency = $document['result'][0]['latency'] / 1000;
            $averageExecutionTime = $document['result'][0]['execution_time'] / 1000;
        } else {
            throw new RuntimeException("Result was not ok: " . var_export($document, true));
        }
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

    /**
     * @param int
     */
    public function countSlowRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $secondsToConsiderJobAsSlow=5
    )
    {
        return count(
            $this->slowArchivedRecentJobs(
                $lowerLimit,
                $upperLimit,
                $secondsToConsiderJobAsSlow
            )
        ) + count(
            $this->slowScheduledRecentJobs(
                $lowerLimit,
                $upperLimit,
                $secondsToConsiderJobAsSlow
            )
        );
    }

    /**
     * @param int
     */
    public function countRecentJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit
    )
    {
        return $this->countRecentArchivedOrScheduledJobsWithManyAttempts(
            $lowerLimit, 
            $upperLimit,
            'archived'
        ) + $this->countRecentArchivedOrScheduledJobsWithManyAttempts(
            $lowerLimit, 
            $upperLimit,
            'scheduled'
        );
    }

    /**
     * @param int
     */
    public function countDelayedScheduledJobs(T\Moment $lowerLimit)
    {
        return $this->scheduled->count([
            'scheduled_at' => [
                '$lte' => T\MongoDate::from($lowerLimit)
            ]
        ]);
    }

    public function delayedScheduledJobs(T\Moment $lowerLimit)
    {
        return $this->map(
            $this->scheduled->find([ 
                'scheduled_at' => [
                    '$lte' => T\MongoDate::from($lowerLimit)
                ]
            ])
        );
    }

    public function recentJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit
    )
    {
        $archived = $this->map(
            $this->recentArchivedOrScheduledJobsWithManyAttempts(
                $lowerLimit, 
                $upperLimit,
                'archived'
            )
        );
        $scheduled = $this->map(
            $this->recentArchivedOrScheduledJobsWithManyAttempts(
                $lowerLimit, 
                $upperLimit,
                'scheduled'
            )
        );
        return array_merge($archived, $scheduled);
    }

    public function slowRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $secondsToConsiderJobAsSlow=5
    )
    {
        $archived= [];
        $archivedArray = $this->slowArchivedRecentJobs(
            $lowerLimit,
            $upperLimit,
            $secondsToConsiderJobAsSlow
        );
        foreach ($archivedArray as $archivedJob) {
            $archived[] = Job::import($archivedJob, $this);
        }
        $scheduled= [];
        $scheduledArray = $this->slowScheduledRecentJobs(
            $lowerLimit,
            $upperLimit,
            $secondsToConsiderJobAsSlow
        );
        foreach ($scheduledArray as $scheduledJob) {
            $scheduled[] = Job::import($scheduledJob, $this);
        }
        return array_merge($archived, $scheduled);
    }
    
    private function slowArchivedRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $secondsToConsiderJobAsSlow
    )
    {
        return $this->archived->aggregate([
            [
                '$match' => [
                    'last_execution.ended_at' => [
                        '$gte' => T\MongoDate::from($lowerLimit),
                    ],
                ]
            ],
            [
                '$project' => [
                    '_id' => '$_id',
                    'execution_time' => [
                        '$subtract' => [
                            '$last_execution.ended_at',
                            '$last_execution.started_at'
                        ]
                    ],
                    'done' => '$done',
                    'created_at' => '$created_at',
                    'locked' => '$locked',
                    'attempts' => '$attempts',
                    'group' => '$group',
                    'workable' => '$workable',
                    'tags' => '$tags',
                    'scheduled_at' => '$scheduled_at',
                    'last_execution' => '$last_execution',
                    'retry_policy' => '$retry_policy',
                ]
            ],
            [
                '$match' => [
                    'execution_time' => [
                        '$gt' => $secondsToConsiderJobAsSlow*1000 
                    ]
                ],
            ],
        ])['result'];
    }

    private function slowScheduledRecentJobs(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $secondsToConsiderJobAsSlow
    )
    {
        return $this->scheduled->aggregate([
            [
                '$match' => [
                    'scheduled_at' => [
                        '$gte' => T\MongoDate::from($lowerLimit),
                        '$lte' => T\MongoDate::from($upperLimit)
                    ],
                    'last_execution.started_at' => [
                        '$exists' => true,
                    ],
                    'last_execution.ended_at' => [
                        '$exists' => true,
                    ],
                ]
            ],
            [
                '$project' => [
                    '_id' => '$_id',
                    'execution_time' => [
                        '$subtract' => [
                            '$last_execution.ended_at',
                            '$last_execution.started_at'
                        ]
                    ],
                    'done' => '$done',
                    'created_at' => '$created_at',
                    'locked' => '$locked',
                    'attempts' => '$attempts',
                    'group' => '$group',
                    'workable' => '$workable',
                    'tags' => '$tags',
                    'scheduled_at' => '$scheduled_at',
                    'last_execution' => '$last_execution',
                    'retry_policy' => '$retry_policy',
                ]
            ],
            [
                '$match' => [
                    'execution_time' => [
                        '$gt' => $secondsToConsiderJobAsSlow*1000 
                    ]
                ],
            ],
        ])['result'];
    }

    private function countRecentArchivedOrScheduledJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $collectionName
    )
    {
        return $this->recentArchivedOrScheduledJobsWithManyAttempts(
            $lowerLimit,
            $upperLimit,
            $collectionName
        )->count();       
    }

    private function recentArchivedOrScheduledJobsWithManyAttempts(
        T\Moment $lowerLimit,
        T\Moment $upperLimit,
        $collectionName
    )
    {
        return $this->{$collectionName}->find([
            'last_execution.ended_at' => [
                '$gte' => T\MongoDate::from($lowerLimit),
                '$lte' => T\MongoDate::from($upperLimit)
             ],
             'attempts' => [
                '$gt' => 1
             ]
        ]);
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
