<?php

namespace Recruiter;

class WorkablePersistenceTest extends \PHPUnit_Framework_TestCase
{
    public function testCanBeExportedAndImported()
    {
        $job = new SomethingWorkable(['key' => 'value']);
        $this->assertEquals(
            $job,
            SomethingWorkable::import($job->export())
        );
    }
}

class SomethingWorkable implements Workable
{
    use WorkableBehaviour;
}
