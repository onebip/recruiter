<?php

namespace Recruiter;

use MongoDB;

class MongoFactoryTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->mongoFactory = new MongoFactory();
        $this->dbHost = 'localhost:27017';
        $this->dbName = 'recruiter';
    }

    public function testShouldCreateAMongoDatabaseConnection()
    {
        $this->assertInstanceOf(
            'MongoDB',
            $this->creationOfDefaultMongoDb()
        );
    }

    public function testWriteConcernIsMajorityByDefault()
    {
        $mongoDb = $this->creationOfDefaultMongoDb();
        $this->assertEquals('majority', $mongoDb->getWriteConcern()['w']);
    }

    public function testShouldOverwriteTheWriteConcernPassedInTheOptions()
    {
        $mongoDb = $this->mongoFactory->getMongoDb(
                $host = 'localhost:27017',
                $options = [
                    'connectTimeoutMS' => '1000',
                    'w' => '0',
                ],
                $dbName = 'recruiter'
        );

        $this->assertEquals('majority', $mongoDb->getWriteConcern()['w']);
    }

    private function creationOfDefaultMongoDb()
    {
        return $this->mongoFactory->getMongoDb(
             $host = $this->dbHost,
             $options = ['connectTimeoutMS' => '1000'],
             $dbName = $this->dbName
        );
    }
}
