<?php
namespace Recruiter\Acceptance;

use Recruiter\Workable\AlwaysFail;
use Recruiter\Workable\FactoryMethodCommand;
use Timeless as T;

class SyncronousExecutionTest extends BaseAcceptanceTest
{
    public function testJobsAreExecutedInOrderOfScheduling()
    {
        $this->enqueueAnAnswerJob(43, T\now()->after(T\seconds(30)));

        $this->enqueueAnAnswerJob(42, T\now());

        $report = $this->recruiter->flushJobsSynchronously();

        $this->assertFalse($report->isThereAFailure());
        $results = $report->toArray();
        $this->assertEquals(42, current($results)->result());
        $this->assertEquals(43, end($results)->result());
    }

    public function testAReportIsReturnedInOrderToSortOutIfAnErrorOccured()
    {
        (new AlwaysFail())
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        $report = $this->recruiter->flushJobsSynchronously();

        $this->assertTrue($report->isThereAFailure());
    }

    private function enqueueAnAnswerJob($answer, $scheduledAt)
    {
        FactoryMethodCommand::from('Recruiter\Acceptance\SyncronousExecutionTestDummyObject::create')
            ->answer($answer)
            ->asJobOf($this->recruiter)
            ->scheduleAt($scheduledAt)
            ->inBackground()
            ->execute();
    }
}

class SyncronousExecutionTestDummyObject
{
    public static function create()
    {
        return new self();
    }

    public function answer($value)
    {
        return $value;
    }

    public function myNeedyMethod(array $retryStatistics)
    {
        return $retryStatistics['retry_number'];
    }
}
