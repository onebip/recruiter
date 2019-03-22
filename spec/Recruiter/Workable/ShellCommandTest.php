<?php
namespace Recruiter\Workable;

use PHPUnit\Framework\TestCase;

class ShellCommandTest extends TestCase
{
    public function testExecutesACommandOnTheShell()
    {
        $workable = ShellCommand::fromCommandLine('echo 42');
        $this->assertEquals('42', $workable->execute());
    }

    public function testCanBeImportedAndExported()
    {
        $workable = ShellCommand::fromCommandLine('echo 42');
        $this->assertEquals(
            $workable,
            ShellCommand::import($workable->export())
        );
    }
}
