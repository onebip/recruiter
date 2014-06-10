<?php
namespace Recruiter;

class RecruitableTest extends \PHPUnit_Framework_TestCase
{
    public function testCanBeExportedAndImported()
    {
        $job = new DummyRecruitableJob(['key' => 'value']);
        $this->assertEquals(
            $job,
            DummyRecruitableJob::import($job->export())
        );
    }
}

class DummyRecruitableJob implements Workable
{
    use Recruitable;
}
