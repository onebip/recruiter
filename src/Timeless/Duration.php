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

    public function seconds()
    {
        return round($this->ms / 1000);
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

    public function parse($string)
    {
        if (is_string($string)) {
            $tokenToFunction = [
                'milliseconds' => 'milliseconds', 'millisecond' => 'milliseconds', 'ms' => 'milliseconds',
                'seconds' => 'seconds', 'second' => 'seconds', 's' => 'seconds',
                'minutes' => 'minutes', 'minute' => 'minutes', 'm' => 'minutes',
                'hours' => 'hours', 'hour' => 'hours', 'h' => 'hours',
                'days' => 'days', 'day' => 'days', 'd' => 'days',
                'weeks' => 'weeks', 'week' => 'weeks', 'w' => 'weeks',
                'months' => 'months', 'month' => 'months', 'mo' => 'months',
                'years' => 'years', 'year' => 'years', 'y' => 'years',
            ];
            $units = implode('|', array_keys($tokenToFunction));
            if (preg_match("/^[^\d]*(?P<quantity>\d+)\s*(?P<unit>{$units})(?:\W.*|$)/", $string, $matches)) {
                return call_user_func('Timeless\\' . $tokenToFunction[$matches['unit']], $matches['quantity']);
            }
            if (!preg_match('/^\d+$/', $string)) {
                throw new InvalidDurationFormat("'{$string}' is not a valid Duration format");
            }
        }
        if (is_numeric($string)) {
            $duration = floor($string);
            throw new InvalidDurationFormat("Maybe you mean '{$duration} seconds' or something like that?");
        }
        throw new InvalidDurationFormat('You need to use strings');
    }
}
