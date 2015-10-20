<?php
namespace Recruiter\Option;

class TargetHostTest extends \PHPUnit_Framework_TestCase
{
    public function testAcceptsManyMongoDbFormats()
    {
        $this->assertEquals(
            [
                'localhost',
                '27018',
                'db_name',
            ],
            TargetHost::parse("localhost:27018/db_name")
        );
        $this->assertEquals(
            [
                'localhost',
                '27017',
                'db_name',
            ],
            TargetHost::parse("mongodb://localhost/db_name")
        );
    }
}
