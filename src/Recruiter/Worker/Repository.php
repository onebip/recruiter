<?php

namespace Recruiter\Worker;

use MongoDB;
use MongoCollection;
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

    public function available()
    {
        return $this->map(
            $this->roster->find(['available' => true])
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
        return array_map(
            function($document) {
                return Worker::import($document, $this->recruiter, $this);
            },
            iterator_to_array($cursor)
        );
    }
}
