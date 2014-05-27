<?php

namespace Recruiter;

use MongoDB;
use MongoId;
use MongoDate;

use Functional as _;

class Recruiter
{
    private $db;
    private $jobs;
    private $workers;

    public function __construct(MongoDB $db)
    {
        $this->db = $db;
        $this->jobs = new Job\Repository($db, $this);
        $this->workers = new Worker\Repository($db, $this);
    }

    public function hire()
    {
        return Worker::workFor($this, $this->workers);
    }

    private function bench($what, $target)
    {
        $startAt = microtime(true);
        $result = $target();
        $stopAt = microtime(true);
        printf('[%s] %fms' . PHP_EOL, $what, ($stopAt - $startAt) * 1000);
        return $result;
    }

    public function assignJobsToWorkers()
    {
        $roster = $this->db->selectCollection('roster');
        $scheduled = $this->db->selectCollection('scheduled');
        /* $contracts = $this->db->selectCollection('contracts'); */

        // PICK AVAILABLE WORKERS
        // TODO: workers should be grouped in Unit based on skills
        $workersAvailableToWork = $this->bench('PICK WORKERS', function() use ($roster) {
            return _\pluck(
                $roster->find(['available' => true], ['_id' => true]), '_id'
            );
        });
        if (count($workersAvailableToWork) === 0) {
            return 0;
        }

        // PICK READY JOBS
        $jobsReadyToBeDone = $this->bench('PICK JOBS', function() use ($scheduled, $workersAvailableToWork) {
            return  _\pluck(
                $scheduled
                    ->find(
                        [   'scheduled_at' => ['$lt' => new MongoDate()],
                            'active' => true,
                            'locked' => false
                        ],
                        [   '_id' => 1
                        ]
                    )
                    ->sort(['scheduled_at' => 1])
                    ->limit(count($workersAvailableToWork)),
                '_id'
            );
        });
        /* var_dump($jobsReadyToBeDone); */

        // CONTRACT
        $gigsInContract = min(count($workersAvailableToWork), count($jobsReadyToBeDone));
        $workersAvailableToWork = array_slice($workersAvailableToWork, 0, $gigsInContract);
        $jobsReadyToBeDone = array_slice($jobsReadyToBeDone, 0, $gigsInContract);
        /* $contracts->save( */
        /*     $contract = [ */
        /*         '_id' => new MongoId(), */
        /*         'created_at' => new MongoDate(), */
        /*         'assignments' => array_combine($workersAvailableToWork, $jobsReadyToBeDone) */
        /*     ] */
        /* ); */

        // LOCK JOBS
        $this->bench('LOCK JOBS', function() use ($scheduled, $jobsReadyToBeDone) {
            $scheduled->update(
                ['_id' => ['$in' => $jobsReadyToBeDone]],
                ['$set' => ['locked' => true]],
                ['multiple' => true]
            );
        });

        // ASSIGN JOBS TO WORKERS
        $this->bench('ASSIGN JOBS', function() use ($roster, $jobsReadyToBeDone, $workersAvailableToWork) {
            foreach ($workersAvailableToWork as $workerAvailableToWork)
            {
                $jobReadyToBeDone = array_shift($jobsReadyToBeDone);
                $roster->update(
                    ['_id' => $workerAvailableToWork],
                    ['$set' => [
                        'available' => false,
                        'assigned_to' => $jobReadyToBeDone,
                        'assigned_since' => new MongoDate()
                    ]]
                );
            }
        });

        /* // ASSIGN JOBS TO WORKERS TROUGH CONTRACT */
        /* $roster->update( */
        /*     ['_id' => ['$in' => $workersAvailableToWork]], */
        /*     [ */
        /*         '$set' => [ */
        /*             'available' => false, */
        /*             'bound_to' => $contract['_id'], */
        /*             'bound_since' => new MongoDate(), */
        /*         ] */
        /*     ], */
        /*     ['multiple' => true] */
        /* ); */

        return $gigsInContract;
    }

    public function jobOf(Workable $workable)
    {
        return Job::around($workable, $this, $this->jobs);
    }

    public function workersAvailableToWork()
    {
        return $this->workers->available();
    }

    public function scheduledJob($id)
    {
        return $this->jobs->scheduled($id);
    }

    public function pickJobFor($worker)
    {
        return $this->jobs->pickFor($worker);
    }
}
