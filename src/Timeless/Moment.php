<?php

namespace Timeless;

class Moment
{
    private $msSince;

    public function __construct($msSince)
    {
        $this->msSince = $msSince;
    }

    public function after(Duration $d)
    {
        return new self($this->msSince + $d->ms());
    }

    public function before(Duration $d)
    {
        return new self($this->msSince - $d->ms());
    }

    public function to($class)
    {
        $seconds = floor($this->msSince / 1000);
        $milliseconds = $this->msSince - $seconds * 1000;
        $microseconds = $milliseconds * 1000;
        return new \MongoDate($seconds, $microseconds);
    }
}
