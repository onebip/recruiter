<?php

namespace Recruiter\Worker;

use MongoDB;
use MongoDB\BSON\UTCDateTime as MongoUTCDateTime;
use Recruiter\Recruiter;
use Recruiter\Worker;

class Repository
{
    private $roster;
    private $recruiter;

    public function __construct(MongoDB\Database $db, Recruiter $recruiter)
    {
        $this->roster = $db->selectCollection('roster');
        $this->recruiter = $recruiter;
    }

    public function save($worker)
    {
        $document = $worker->export();
        $result = $this->roster->replaceOne(
            ['_id' => $document['_id']],
            $document,
            ['upsert' => true]
        );
    }

    public function atomicUpdate($worker, array $changeSet)
    {
        $this->roster->updateOne(
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
                '$lt' => new MongoUTCDateTime($consideredDeadAt)]
            ],
            ['projection' => ['_id' => true, 'assigned_to' => true]]
        );
    }

    public function retireWorkerWithIdIfNotAssigned($id)
    {
        $result = $this->roster->deleteOne(['_id' => $id, 'available' => true]);

        return $result->getDeletedCount() > 0;
    }

    public function retireWorkerWithId($id)
    {
        $this->roster->deleteOne(['_id' => $id]);
    }

    public function retireWorkerWithPid($pid)
    {
        $this->roster->deleteOne(['pid' => intval($pid)]);
    }
}
