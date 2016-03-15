<?php
namespace Recruiter\Job;

use Recruiter\Job;
use Recruiter\JobToSchedule;
use MongoClient;
use DateTime;

class RepositoryTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->recruiterDb = (new MongoClient('localhost:27017'))->selectDB('recruiter');
        $this->recruiterDb->drop();
        $this->repository = new Repository($this->recruiterDb);
    }

    public function testQueued()
    {
        $this->aJob()->taggedAs('generic')->inBackground()->execute();
        $this->aJob()->taggedAs('generic')->inBackground()->execute();
        $this->aJob()->taggedAs('fast-lane')->inBackground()->execute();
        $this->assertEquals(3, $this->repository->queued());
        $this->assertEquals(2, $this->repository->queued('generic'));
        $this->assertEquals(1, $this->repository->queued('fast-lane'));
    }

    private function aJob()
    {
        $workable = $this
            ->getMockBuilder('Recruiter\Workable')
            ->getMock();

        return new JobToSchedule(Job::around($workable, $this->repository));
    }
}
