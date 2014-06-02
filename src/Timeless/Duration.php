<?php

namespace Timeless;

class Duration
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

    public function sinceNow()
    {
        return $this->since(now());
    }

    public function fromNow()
    {
        return $this->from(now());
    }

    public function since(Moment $reference)
    {
        return $reference->before($this);
    }

    public function from(Moment $reference)
    {
        return $reference->after($this);
    }
}
