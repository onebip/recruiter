<?php

namespace Timeless;

use DateTime;
use DateTimeZone;

class Moment
{
    private $ms;

    public static function fromTimestamp($ts)
    {
        return new self($ts * 1000);
    }

    public function __construct($ms)
    {
        $this->ms = $ms;
    }

    public function milliseconds()
    {
        return $this->ms;
    }

    public function ms()
    {
        return $this->ms;
    }

    public function seconds()
    {
        return $this->s();
    }

    public function s()
    {
        return round($this->ms / 1000.0);
    }

    public function after(Interval $d)
    {
        return new self($this->ms + $d->ms());
    }

    public function before(Interval $d)
    {
        return new self($this->ms - $d->ms());
    }

    public function isAfter(Moment $m)
    {
        return $this->ms >= $m->ms();
    }

    public function isBefore(Moment $m)
    {
        return $this->ms <= $m->ms();
    }

    public function toSecondPrecision()
    {
        return new self($this->s() * 1000);
    }

    public function format()
    {
        return (new DateTime('@' . $this->s(), new DateTimeZone('UTC')))->format(DateTime::RFC3339);
    }
}
