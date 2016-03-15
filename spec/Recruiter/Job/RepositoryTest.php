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
        $this->aJob()->inBackground()->execute();
        $this->assertEquals(1, $this->repository->queued());
    }

    private function aJob()
    {
        $workable = $this
            ->getMockBuilder('Recruiter\Workable')
            ->getMock();

        return new JobToSchedule(Job::around($workable, $this->repository));
    }
}
