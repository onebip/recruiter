<?php

namespace Recruiter\Worker;

use MongoId;
use MongoDB;
use MongoCollection;
use MongoDate;
use Recruiter\Recruiter;
use Recruiter\Worker;

class Repository
{
    private $roster;
    private $recruiter;

    public function __construct(MongoDB $db, Recruiter $recruiter)
    {
        $this->roster = $db->selectCollection('roster');
        $this->recruiter = $recruiter;
    }

    public function save($worker)
    {
        $this->roster->save($worker->export());
    }

    public function atomicUpdate($worker, array $changeSet)
    {
        $this->roster->update(
            ['_id' => $worker->id()],
            ['$set' => $changeSet]
        );
    }

    public function refresh($worker)
    {
        $worker->updateWith(
            $this->roster->findOne(['_id' => $worker->id()])
        );
    }

    public function deadWorkers($consideredDeadAt)
    {
        return $this->roster->find(
            ['last_seen_at' => [
                '$lt' => new MongoDate($consideredDeadAt->getTimestamp())]
            ],
            ['_id' => true, 'assigned_to' => true]
        );
    }

    public function retireWorkerWithIdIfNotAssigned($id)
    {
        return $this->roster->remove(['_id' => $id, 'available' => true])['n'] > 0;
    }

    public function retireWorkerWithId($id)
    {
        $this->roster->remove(['_id' => $id]);
    }

    public function retireWorkerWithPid($pid)
    {
        $this->roster->remove(['pid' => intval($pid)]);
    }
}
