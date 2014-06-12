<?php

namespace Sink;

use Iterator;
use ArrayAccess;

class BlackHole implements Iterator, ArrayAccess
{
    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    public function __set($name, $value)
    {
    }

    public function __get($name)
    {
        return $this;
    }

    public function __isset($name)
    {
        return false;
    }

    public function __unset($name)
    {
    }

    public function __call($name, $arguments)
    {
        return $this;
    }

    public function __toString()
    {
        return '';
    }

    public function __invoke()
    {
        return $this;
    }

    public function __clone()
    {
        return new self();
    }

    public static function __callStatic($name, $args)
    {
        return new self();
    }

    // Iterator Interface

    public function current()
    {
        return $this;
    }

    public function key()
    {
        return $this;
    }

    public function next()
    {
    }

    public function rewind()
    {
    }

    public function valid()
    {
        return false;
    }

    // ArrayAccess Interface

    public function offsetExists($offset)
    {
        return false;
    }

    public function offsetGet($offset)
    {
        return $this;
    }

    public function offsetSet($offset, $value)
    {
    }

    public function offsetUnset($offset)
    {
    }
}
