<?php

namespace Recruiter;

use MongoClient;

class Factory
{
    public function getMongoDb($hosts, $options, $dbName)
    {
        $optionsWithMajorityConcern = array_merge($options, ['w' => 'majority']);
        return (new MongoClient($hosts, $optionsWithMajorityConcern))->selectDb($dbName);
    }
}
