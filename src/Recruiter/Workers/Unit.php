<?php

namespace Recruiter\Workers;

use MongoId;
use MongoDate;

class Unit
{
    private $ids;
    private $roster;

    public function __construct($ids, $roster)
    {
        $this->ids = $ids;
        $this->roster = $roster;
    }

    public function pickFrom($jobs)
    {
        var_dump('Unit::pickFrom ready jobs');
        return $jobs
            ->pick($skills = null, count($this->ids))
            ->lock();
    }

    public function combineWith($jobIds)
    {
        $contract = [
            '_id' => new MongoId(),
            'created_at' => new MongoDate(),
            'assignments' => array_combine($this->ids, $jobIds)
        ];
        $this->roster->update(
            ['_id' => ['$in' => $this->ids]],
            [
                '$set' => [
                    'available' => false,
                    'bound_to' => $contract['_id'],
                    'bound_since' => new MongoDate(),
                ],
                '$unset' => [
                    'available_since' => true,
                ]
            ],
            ['multiple' => true]
        );
        return $contract;
    }
}
