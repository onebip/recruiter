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
