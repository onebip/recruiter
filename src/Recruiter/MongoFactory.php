<?php

namespace Recruiter;

use MongoClient;

class MongoFactory
{
    public function getMongoDb($hosts, $options, $dbName)
    {
        $optionsWithMajorityConcern = array_merge($options, ['w' => 'majority']);
        return $db = (new MongoClient($hosts, $optionsWithMajorityConcern))->selectDb($dbName);
    }
}
