<?php
namespace Recruiter\Option;

class TargetHostTest extends \PHPUnit_Framework_TestCase
{
    public function testAcceptsTheMostBasicMongoDbFormat()
    {
        $this->assertEquals(
            [
                'localhost',
                'recruiter',
                []
            ],
            TargetHost::parse("localhost")
        );
    }

    public function testAcceptsPortAndDbName()
    {
        $this->assertEquals(
            [
                'localhost:27018',
                'db_name',
                []
            ],
            TargetHost::parse("localhost:27018/db_name")
        );
    }

    public function testAcceptsMongoDbSchemePrefix()
    {
        $this->assertEquals(
            [
                'localhost',
                'db_name',
                []
            ],
            TargetHost::parse("mongodb://localhost/db_name")
        );
    }

    public function testAcceptParametersInTheQueryString()
    {
        $this->assertEquals(
            [
                'localhost',
                'db_name',
                ['replicaSet' => 'rs'],
            ],
            TargetHost::parse("mongodb://localhost/db_name?replicaSet=rs")
        );
    }

    public function testMultipleHosts()
    {
        $this->assertEquals(
            [
                'localhost:27017,localhost:27018,localhost',
                'db_name',
                ['replicaSet' => 'rs'],
            ],
            TargetHost::parse("mongodb://localhost:27017,localhost:27018,localhost/db_name?replicaSet=rs")
        );
    }
}
