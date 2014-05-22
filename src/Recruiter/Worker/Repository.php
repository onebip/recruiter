<?php

namespace Recruiter\Worker;

use MongoDB;
use MongoCollection;
use Recruiter\Recruiter;
use Recruiter\Workers;
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

    public function available()
    {
        return Workers\AvailableToWork::from(
            $this->roster->find(['available' => true], ['_id' => true, 'skills' => true]),
            $this
        );
    }

    public function save($worker)
    {
        $this->roster->save($worker->export());
    }

    public function refresh($worker)
    {
        $worker->updateWith(
            $this->roster->findOne(['_id' => $worker->id()])
        );
    }

    private function map($cursor)
    {
        $workers = [];
        while ($cursor->hasNext()) {
            $workers[] = Worker::import($cursor->getNext(), $this->recruiter, $this);
        }
        return $workers;
    }
}
