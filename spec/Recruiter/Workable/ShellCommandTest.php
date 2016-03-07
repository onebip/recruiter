<?php
namespace Recruiter\Workable;

class ShellCommandTest extends \PHPUnit_Framework_TestCase
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
