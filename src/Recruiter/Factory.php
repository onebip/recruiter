<?php

namespace Recruiter;

use MongoClient;
use MongoConnectionException;
use Recruiter\Infrastructure\Persistence\Mongodb\URI;
use UnexpectedValueException;

class Factory
{
    public function getMongoDb(URI $uri, array $options = []) //FIXME:! remove the old method and rename this one
    {
        try {
            $optionsWithMajorityConcern = array_merge($uri->options(), $options, ['w' => 'majority']);
            foreach ($optionsWithMajorityConcern as $optionKey => $optionValue) {
                if (empty($optionValue)) {
                    unset($optionsWithMajorityConcern[$optionKey]);
                }
            }

            return (new MongoClient($uri->host(), $optionsWithMajorityConcern))->selectDb($uri->dbName());
        } catch (MongoConnectionException $e) {
            throw new UnexpectedValueException(
                sprintf(
                    "'No MongoDB running at '%s'",
                    $uri->__toString()
                )
            );
        }
    }
}
