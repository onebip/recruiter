<?php

namespace Recruiter;

use PHPUnit\Framework\TestCase;

class WorkablePersistenceTest extends TestCase
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
