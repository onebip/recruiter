<?php

namespace Recruiter;

use MongoClient;

class Factory
{
    public function getMongoDb($hosts, $options, $dbName)
    {
        $optionsWithMajorityConcern = array_merge($options, ['w' => 'majority']);
        foreach ($optionsWithMajorityConcern as $optionKey => $optionValue) {
            if (empty($optionValue)) {
                unset($optionsWithMajorityConcern[$optionKey]);
            }
        }
        return (new MongoClient($hosts, $optionsWithMajorityConcern))->selectDb($dbName);
    }
}
