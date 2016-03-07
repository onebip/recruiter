<?php
namespace Recruiter\Workable;

class FactoryMethodCommandTest extends \PHPUnit_Framework_TestCase
{
    public function testExecutedACommandReachableFromAStaticFactoryMethod()
    {
        $workable = FactoryMethodCommand::from('Recruiter\Workable\DummyFactory::create')
            ->myObject()
            ->myMethod('answer', 42);
        $this->assertEquals('42', $workable->execute());
    }

    public function testCanBeImportedAndExported()
    {
        $workable = FactoryMethodCommand::from('Recruiter\Workable\DummyFactory::create')
            ->myObject()
            ->myMethod('answer', 42);
        $this->assertEquals(
            $workable,
            FactoryMethodCommand::import($workable->export())
        );
    }
}

class DummyFactory
{
    public static function create()
    {
        return new self();
    }

    public function myObject()
    {
        return new DummyObject();
    }
}

class DummyObject
{
    public function myMethod($what, $value)
    {
        return $value;
    }
}

