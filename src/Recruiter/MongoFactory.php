<?php

namespace Recruiter;

use MongoClient;

class MongoFactory
{
    public function getMongoDb($hosts, $options, $dbName)
    {
        return $db = (new MongoClient($hosts, $options))->selectDb($dbName);
    }
}
