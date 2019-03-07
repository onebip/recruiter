<?php

namespace Recruiter;

use MongoDB;
use PHPUnit\Framework\TestCase;
use Recruiter\Infrastructure\Persistence\Mongodb\URI as MongoURI;

class FactoryTest extends TestCase
{
    /**
     * @var Factory
     */
    private $factory;

    /**
     * @var string
     */
    private $dbHost;

    /**
     * @var string
     */
    private $dbName;

    protected function setUp(): void
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
            MongoURI::from('mongodb://localhost:27017/recruiter'),
            [
                'connectTimeoutMS' => 1000,
                'w' => '0',
            ]
        );

        $this->assertEquals('majority', $mongoDb->getWriteConcern()['w']);
    }

    private function creationOfDefaultMongoDb()
    {
        return $this->factory->getMongoDb(
            MongoURI::from(sprintf('mongodb://%s/%s', $this->dbHost, $this->dbName)),
            [
                'connectTimeoutMS' => 1000,
                'w' => '0',
            ]
        );
    }
}
