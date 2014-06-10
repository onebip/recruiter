<?php
namespace Recruiter;

class WorkableTest extends \PHPUnit_Framework_TestCase
{
    public function testParametersAreAccessibleToTheWorkable()
    {
        $job = new DummyBaseWorkable(['key' => 'value']);
        $this->assertEquals(
            ['key' => 'value'],
            DummyBaseWorkable::import($job->export())->execute()
        );
    }
}

class DummyBaseWorkable extends BaseWorkable
{
    public function execute()
    {
        return $this->parameters;
    }
}

