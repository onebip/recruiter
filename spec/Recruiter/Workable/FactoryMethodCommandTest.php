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

    public function testPassesRetryStatisticsAsAnAdditionalArgumentToTheLastMethodToCall()
    {
        $workable = FactoryMethodCommand::from('Recruiter\Workable\DummyFactory::create')
            ->myObject()
            ->myNeedyMethod();
        $workable->execute(['retry_number' => 0]);
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

    public function myNeedyMethod(array $retryStatistics)
    {
        return 42;
    }
}

