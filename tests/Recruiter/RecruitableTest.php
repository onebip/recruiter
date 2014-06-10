<?php
namespace Recruiter;

class RecruitableTest extends \PHPUnit_Framework_TestCase
{
    public function testCanBeExportedAndImported()
    {
        $job = new DummyRecruitable(['key' => 'value']);
        $this->assertEquals(
            $job,
            DummyRecruitable::import($job->export())
        );
    }
}

class DummyRecruitable implements Workable
{
    use Recruitable;
}
