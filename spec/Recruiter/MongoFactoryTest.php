<?php

namespace Recruiter;

use MongoDB;

class MongoFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldCreateAMongoDatabaseConnection()
    {
        $mongoFactory = new MongoFactory();
        $this->assertInstanceOf(
            'MongoDB',
            $mongoFactory->getMongoDb(
                $host = 'localhost:27017',
                $options = ['connectTimeoutMS' => '1000'],
                $dbName = 'recruiter'
        ));
    }
}
