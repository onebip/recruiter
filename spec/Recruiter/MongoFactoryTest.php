<?php

namespace Recruiter;

use MongoDB;

class MongoFactoryTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->mongoFactory = new MongoFactory();
    }

    public function testShouldCreateAMongoDatabaseConnection()
    {
        $this->assertInstanceOf(
            'MongoDB',
            $this->mongoFactory->getMongoDb(
                $host = 'localhost:27017',
                $options = ['connectTimeoutMS' => '1000'],
                $dbName = 'recruiter'
        ));
    }

    public function testWriteConcernIsMajorityByDefault()
    {
        $mongoDb = $this->mongoFactory->getMongoDb(
                $host = 'localhost:27017',
                $options = ['connectTimeoutMS' => '1000'],
                $dbName = 'recruiter'
        );

        $this->assertEquals('majority', $mongoDb->getWriteConcern()['w']);
    }
}
