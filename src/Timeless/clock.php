<?php

namespace Timeless;

class Moment
{
    private $time;

    public function __construct($time)
    {
        $this->time = $time;
    }

    public function to($class)
    {
        $seconds = floor($this->time / 1000);
        $milliseconds = $this->time - $seconds * 1000;
        $microseconds = $milliseconds * 1000;
        return new \MongoDate($seconds, $microseconds);
    }
}

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

function clock($clock = null)
{
    global $__2852bec4cda046fca0e5e21dc007935c;
    $__2852bec4cda046fca0e5e21dc007935c =
        $clock ?: (
            $__2852bec4cda046fca0e5e21dc007935c ?: new Clock()
        );
    return $__2852bec4cda046fca0e5e21dc007935c;
}
