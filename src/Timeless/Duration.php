<?php

namespace Timeless;

class Duration
{
    const MILLISECONDS_IN_SECONDS = 1000;
    const MILLISECONDS_IN_MINUTES = 60000;
    const MILLISECONDS_IN_HOURS = 3600000;
    const MILLISECONDS_IN_DAYS = 86400000;
    const MILLISECONDS_IN_WEEKS = 604800000;
    const MILLISECONDS_IN_MONTHS = 2592000000;
    const MILLISECONDS_IN_YEARS = 31104000000;

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
        return (int) floor($this->ms / self::MILLISECONDS_IN_SECONDS);
    }

    public function minutes()
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_MINUTES);
    }

    public function hours()
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_HOURS);
    }

    public function days()
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_DAYS);
    }

    public function weeks()
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_WEEKS);
    }

    public function months()
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_MONTHS);
    }

    public function years()
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_YEARS);
    }

    public function ago()
    {
        return $this->since(now());
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

    public function format($format)
    {
        if (is_string($format)) {
            $availableFormatsTable = [
                'ms' => ['milliseconds', 'ms', 'ms'],
                's' => ['seconds', 's', 's'],
                'm' => ['minutes', 'm', 'm'],
                'h' => ['hours', 'h', 'h'],
                'd' => ['days', 'd', 'd'],
                'w' => ['weeks', 'w', 'w'],
                'mo' => ['months', 'mo', 'mo'],
                'y' => ['years', 'y', 'y'],
                'milliseconds' => ['milliseconds', ' milliseconds', ' millisecond'],
                'seconds' => ['seconds', ' seconds', ' second'],
                'minutes' => ['minutes', ' minutes', ' minute'],
                'hours' => ['hours', ' hours', ' hour'],
                'days' => ['days', ' days', ' day'],
                'weeks' => ['weeks', ' weeks', ' week'],
                'months' => ['months', ' months', ' month'],
                'years' => ['years', ' years', ' year'],
            ];
            $format = trim($format);
            if (array_key_exists($format, $availableFormatsTable)) {
                $amountOfTime = call_user_func([$this, $availableFormatsTable[$format][0]]);
                $unitOfTime = $amountOfTime === 1 ?
                    $availableFormatsTable[$format][2] :
                    $availableFormatsTable[$format][1];
                return sprintf('%d%s', $amountOfTime, $unitOfTime);
            }
            throw new InvalidDurationFormat("'{$format}' is not a valid Duration format");
        }
        throw new InvalidDurationFormat('You need to use strings');
    }

    public static function parse($string)
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
