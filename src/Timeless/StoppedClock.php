<?php

namespace Timeless;

class StoppedClock
{
    private $now;

    public function __construct(Moment $now)
    {
        $this->now = $now;
    }

    public function now()
    {
        return $this->now;
    }

    public function start()
    {
        return clock(new Clock());
    }
}
