<?php

namespace Recruiter;

use MongoDB;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->factory = new Factory();
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
        $mongoDb = $this->factory->getMongoDb(
                $host = 'localhost:27017',
                $options = [
                    'connectTimeoutMS' => 1000,
                    'w' => '0',
                ],
                $dbName = 'recruiter'
        );

        $this->assertEquals('majority', $mongoDb->getWriteConcern()['w']);
    }

    private function creationOfDefaultMongoDb()
    {
        return $this->factory->getMongoDb(
             $host = $this->dbHost,
             $options = ['connectTimeoutMS' => 1000],
             $dbName = $this->dbName
        );
    }
}
