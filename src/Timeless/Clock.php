<?php

namespace Timeless;

class Clock
{
    public function stop()
    {
        return clock(new StoppedClock($this->now()));
    }

    public function now()
    {
        return new Moment(round(microtime(true) * 1000));
    }
}
