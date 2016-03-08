<?php

namespace Recruiter\Acceptance;

use Recruiter\Worker\Repository;
use Recruiter\Recruiter;

class WorkerRepositoryTest extends BaseAcceptanceTest
{
    public function setUp()
    {
        parent::setUp();
        $this->repository = new Repository(
            $this->recruiter, new Recruiter($this->recruiter)
        );
    }

    /**
     * @group acceptance
     */
    public function testRetireWorkerWithPid()
    {
        $this->givenWorkerWithPid(10);
        $this->assertEquals(1, $this->numberOfWorkers());
        $this->repository->retireWorkerWithPid(10);
        $this->assertEquals(0, $this->numberOfWorkers());
    }

    protected function givenWorkerWithPid($pid)
    {
        $this->roster->save(['pid' => $pid]);
    }
}
