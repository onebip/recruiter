<?php

namespace Recruiter;

use Onebip;
use MongoId;
use MongoCollection;
use MongoWriteConcernException;
use Exception;
use InvalidArgumentException;

use Timeless as T;
use Timeless\Moment;

use Recruiter\RetryPolicy;
use Recruiter\Job\Repository;
use Recruiter\Job\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Job
{
    private $status;
    private $workable;
    private $retryPolicy;
    private $repository;
    private $lastJobExecution;

    public static function around(Workable $workable, Repository $repository)
    {
        return new self(
            self::initialize(),
            $workable,
            ($workable instanceof Retriable) ?
                $workable->retryWithPolicy() : new RetryPolicy\DoNotDoItAgain(),
            $repository
        );
    }

    public static function import($document, Repository $repository)
    {
        return new self(
            $document,
            WorkableInJob::import($document),
            RetryPolicyInJob::import($document),
            $repository
        );
    }

    public function __construct($status, Workable $workable, RetryPolicy $retryPolicy, Repository $repository)
    {
        $this->status = $status;
        $this->workable = $workable;
        $this->retryPolicy = $retryPolicy;
        $this->repository = $repository;
        $this->lastJobExecution = new JobExecution();
    }

    public function id()
    {
        return $this->status['_id'];
    }

    public function numberOfAttempts()
    {
        return $this->status['attempts'];
    }

    public function retryWithPolicy(RetryPolicy $retryPolicy)
    {
        $this->retryPolicy = $retryPolicy;
        return $this;
    }

    public function taggedAs(array $tags)
    {
        $this->status['tags'] = $tags;
        return $this;
    }

    public function scheduleAt(Moment $at)
    {
        $this->status['locked'] = false;
        $this->status['scheduled_at'] = T\MongoDate::from($at);
        return $this;
    }

    public function methodToCallOnWorkable($method)
    {
        if (!method_exists($this->workable, $method)) {
            throw new Exception("Unknown method '$method' on workable instance");
        }
        $this->status['workable']['method'] = $method;
    }

    public function execute(EventDispatcherInterface $eventDispatcher)
    {
        $methodToCall = $this->status['workable']['method'];
        try {
            $this->beforeExecution();
            $result = $this->workable->$methodToCall($this->retryStatistics());
            $this->afterExecution($result);
            return $result;
        } catch(\Exception $exception) {
            $this->afterFailure($exception, $eventDispatcher);
        }
    }

    public function retryStatistics()
    {
        return [
            'job_id' => (string) $this->id(),
            'retry_number' => $this->status['attempts'],
            'last_execution' => array_key_exists('last_execution', $this->status)
                ? $this->status['last_execution']
                : null,
        ];
    }

    public function save()
    {
        $this->repository->save($this);
    }

    public function archive($why)
    {
        $this->status['why'] = $why;
        $this->status['active'] = false;
        $this->status['locked'] = false;
        unset($this->status['scheduled_at']);
        $this->repository->archive($this);
    }

    public function export()
    {
        return array_merge(
            $this->status,
            $this->lastJobExecution->export(),
            WorkableInJob::export($this->workable, $this->status['workable']['method']),
            RetryPolicyInJob::export($this->retryPolicy)
        );
    }

    public function beforeExecution()
    {
        $this->status['attempts'] += 1;
        $this->lastJobExecution->started($this->scheduledAt());
        if ($this->hasBeenScheduled()) {
            $this->save();
        }
        return $this;
    }

    public function afterExecution($result)
    {
        $this->status['done'] = true;
        $this->lastJobExecution->completedWith($result);
        if ($this->hasBeenScheduled()) {
            $this->archive('done');
        }
        return $this;
    }

    /**
     * @return boolean
     */
    public function done()
    {
        return $this->status['done'];
    }

    private function afterFailure($exception, $eventDispatcher)
    {
        $this->lastJobExecution->failedWith($exception);
        $jobAfterFailure = new JobAfterFailure($this, $this->lastJobExecution);
        $this->retryPolicy->schedule($jobAfterFailure);
        $wasLastFailure = $jobAfterFailure->archiveIfNotScheduled();
        if ($wasLastFailure) {
            $eventDispatcher->dispatch('job.failure.last', new Event($this->export()));
        }
    }

    private function hasBeenScheduled()
    {
        return array_key_exists('scheduled_at', $this->status);
    }

    private function scheduledAt()
    {
        if ($this->hasBeenScheduled()) {
            return T\MongoDate::toMoment($this->status['scheduled_at']);
        }
    }

    private static function initialize()
    {
        return array_merge(
            [
                '_id' => new MongoId(),
                'active' => true,
                'done' => false,
                'created_at' => T\MongoDate::now(),
                'locked' => false,
                'attempts' => 0,
                'tags' => [],
            ],
            WorkableInJob::initialize(),
            RetryPolicyInJob::initialize()
        );
    }

    public static function pickReadyJobsForWorkers(MongoCollection $collection, $worksOn, $workers)
    {
        $jobs = Onebip\array_pluck(
            iterator_to_array(
                $collection
                    ->find(
                        (Worker::canWorkOnAnyJobs($worksOn) ?
                            [   'scheduled_at' => ['$lt' => T\MongoDate::now()],
                                'active' => true,
                                'locked' => false,
                            ] :
                            [   'scheduled_at' => ['$lt' => T\MongoDate::now()],
                                'active' => true,
                                'locked' => false,
                                'tags' => $worksOn,
                            ]
                        ),
                        [   '_id' => 1
                        ]
                    )
                    ->sort(['scheduled_at' => 1])
                    ->limit(count($workers))
            ),
            '_id'
        );
        if (count($jobs) > 0) {
            return [$worksOn, $workers, $jobs];
        }
    }

    public static function rollbackLockedNotIn(MongoCollection $collection, array $excluded)
    {
        try {
            return $collection->update(
                [
                    'locked' => true,
                    '_id' => ['$nin' => $excluded],
                ],
                [
                    '$set' => [
                        'locked' => false,
                    ]
                ],
                [
                    'multiple' => true,
                ]
            )['n'];
        } catch (MongoWriteConcernException $e) {
            throw new InvalidArgumentException("Not valid excluded jobs filter: " . var_export($excluded, true), -1, $e);
        }
    }

    public static function lockAll(MongoCollection $collection, $jobs)
    {
        $collection->update(
            ['_id' => ['$in' => array_values($jobs)]],
            ['$set' => ['locked' => true]],
            ['multiple' => true]
        );
    }
}
