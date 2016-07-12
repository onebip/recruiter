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
use Recruiter\Job\EventListener;
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
        if (!empty($tags)) {
            $this->status['tags'] = $tags;
        }

        return $this;
    }

    public function inGroup($group)
    {
        if (is_array($group)) {
            throw new \RuntimeException(
                "Group can be only single string, for other uses use `taggedAs` method.
                Received group: `" . var_export($group, true) . "`"
            );
        }
        if (!empty($group)) {
            $this->status['group'] = $group;
        }
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
            $this->beforeExecution($eventDispatcher);
            $result = $this->workable->$methodToCall($this->retryStatistics());
            $this->afterExecution($result, $eventDispatcher);
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
            'maximum' => $this->retryPolicy->maximumNumberOfRetries(),
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

    public function beforeExecution(EventDispatcherInterface $eventDispatcher)
    {
        $this->status['attempts'] += 1;
        $this->lastJobExecution->started($this->scheduledAt());
        $this->emit('job.started', $eventDispatcher);
        if ($this->hasBeenScheduled()) {
            $this->save();
        }
        return $this;
    }

    public function afterExecution($result, EventDispatcherInterface $eventDispatcher)
    {
        $this->status['done'] = true;
        $this->lastJobExecution->completedWith($result);
        $this->emit('job.ended', $eventDispatcher);
        if ($this->hasBeenScheduled()) {
            $this->archive('done');
        }
        return $this;
    }

    public function done()
    {
        return $this->status['done'];
    }

    private function afterFailure($exception, $eventDispatcher)
    {
        $this->lastJobExecution->failedWith($exception);
        $jobAfterFailure = new JobAfterFailure($this, $this->lastJobExecution);
        $this->retryPolicy->schedule($jobAfterFailure);
        $jobAfterFailure->archiveIfNotScheduled();
        $this->emit('job.ended', $eventDispatcher);
        if (!$this->hasBeenScheduled()) {
            $this->emit('job.failure.last', $eventDispatcher);
        }
    }

    private function emit($eventType, $eventDispatcher)
    {
        $event = new Event($this->export());
        $eventDispatcher->dispatch($eventType, $event);
        if ($this->workable instanceof EventListener) {
            $this->workable->onEvent($eventType, $event);
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
                'done' => false,
                'created_at' => T\MongoDate::now(),
                'locked' => false,
                'attempts' => 0,
                'group' => 'generic',
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
                                'locked' => false,
                            ] :
                            [   'scheduled_at' => ['$lt' => T\MongoDate::now()],
                                'locked' => false,
                                'group' => $worksOn,
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
