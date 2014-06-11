<?php

namespace Timeless;

class Moment
{
    private $ms;

    public function __construct($ms)
    {
        $this->ms = $ms;
    }

    public function ms()
    {
        return $this->ms;
    }

    public function milliseconds()
    {
        return $this->ms;
    }

    public function after(Interval $d)
    {
        return new self($this->ms + $d->ms());
    }

    public function before(Interval $d)
    {
        return new self($this->ms - $d->ms());
    }
}
