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
        return $jobs
            ->pick($skills = null, count($this->ids))
            ->lock();
    }

    public function combineWith($jobIds)
    {
        $numberOfPossibleAssignments = min(count($jobIds), count($this->ids));
        $jobIds = array_slice($jobIds, 0, $numberOfPossibleAssignments);
        $workerIds = array_slice($this->ids, 0, $numberOfPossibleAssignments);
        $contract = [
            '_id' => new MongoId(),
            'created_at' => new MongoDate(),
            'assignments' => array_combine($workerIds, $jobIds)
        ];
        $this->roster->update(
            ['_id' => ['$in' => $workerIds]],
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
