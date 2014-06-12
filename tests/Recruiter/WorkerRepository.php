<?php

namespace Recruiter;

use Recruiter\Worker\Repository;

class WorkerRepositoryTest extends BaseAcceptanceTest
{
    public function setUp()
    {
        parent::setUp();
        $this->repository = new Repository($this->recruiter, new Recruiter($this->recruiter));
    }
    /**
     * @group acceptance
     */
    public function testRetireWorkerWithPid()
    {
        /* $this->givenWorkerWithPid(10); */
    }
}
