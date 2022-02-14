<?php

namespace Sink;

use PHPUnit\Framework\TestCase;

class BlackHoleTest extends TestCase
{
    public function testMethodCall()
    {
        $instance = new BlackHole();
        $this->assertInstanceOf('Sink\BlackHole', $instance->whateverMethod());
    }

    public function testGetter()
    {
        $instance = new BlackHole();
        $this->assertInstanceOf('Sink\BlackHole', $instance->whateverProperty);
    }

    public function testSetterReturnsTheValue()
    {
        $instance = new BlackHole();
        $this->assertEquals(42, $instance->whateverProperty = 42);
    }

    public function testNothingIsSet()
    {
        $instance = new BlackHole();
        $instance->whateverProperty = 42;
        $this->assertFalse(isset($instance->whateverProperty));
    }

    public function testToString()
    {
        $instance = new BlackHole();
        $this->assertEquals('', (string) $instance);
    }

    public function testInvoke()
    {
        $instance = new BlackHole();
        $this->assertInstanceOf('Sink\BlackHole', $instance());
    }

    public function testCallStatic()
    {
        $instance = BlackHole::whateverStaticMethod();
        $this->assertInstanceOf('Sink\BlackHole', $instance);
    }

    public function testIsIterableButItIsAlwaysEmpty()
    {
        $instance = new BlackHole();
        $this->assertEmpty(iterator_to_array($instance));
    }

    public function testIsAccessibleAsAnArrayAlwaysGetItself()
    {
        $instance = new BlackHole();
        $this->assertInstanceOf('Sink\BlackHole', $instance[42]);
        $this->assertInstanceOf('Sink\BlackHole', $instance['aString']);
        $this->assertInstanceOf('Sink\BlackHole', $instance[[1,2,3]]);
    }

    public function testIsAccessibleAsAnArrayExists()
    {
        $instance = new BlackHole();
        $this->assertFalse(array_key_exists(42, (array) $instance));
    }
}
