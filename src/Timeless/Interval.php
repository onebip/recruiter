<?php
namespace Timeless;

use DateInterval;
use DateTimeImmutable;

class Interval
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
        $this->ms = intval($ms);
    }

    public function us(): int
    {
        return $this->ms * 1000;
    }

    public function microseconds(): int
    {
        return $this->ms * 1000;
    }

    public function ms(): int
    {
        return $this->ms;
    }

    public function milliseconds(): int
    {
        return $this->ms;
    }

    public function seconds(): int
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_SECONDS);
    }

    public function minutes(): int
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_MINUTES);
    }

    public function hours(): int
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_HOURS);
    }

    public function days(): int
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_DAYS);
    }

    public function weeks(): int
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_WEEKS);
    }

    public function months(): int
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_MONTHS);
    }

    public function years(): int
    {
        return (int) floor($this->ms / self::MILLISECONDS_IN_YEARS);
    }

    public function ago(): Moment
    {
        return $this->since(now());
    }

    public function sinceNow(): Moment
    {
        return $this->since(now());
    }

    public function fromNow(): Moment
    {
        return $this->from(now());
    }

    public function since(Moment $reference): Moment
    {
        return $reference->before($this);
    }

    public function from(Moment $reference): Moment
    {
        return $reference->after($this);
    }

    public function multiplyBy($multiplier): self
    {
        return new self($this->ms * $multiplier);
    }

    public function add(Interval $interval): self
    {
        return new self($this->ms + $interval->ms);
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
                $callable = [$this, $availableFormatsTable[$format][0]];
                if (!is_callable($callable)) {
                    throw new \RuntimeException("function `{$availableFormatsTable[$format][0]}` does not exists");
                }

                $amountOfTime = call_user_func($callable);
                $unitOfTime = $amountOfTime === 1 ?
                    $availableFormatsTable[$format][2] :
                    $availableFormatsTable[$format][1];
                return sprintf('%d%s', $amountOfTime, $unitOfTime);
            }
            throw new InvalidIntervalFormat("'{$format}' is not a valid Interval format");
        }
        throw new InvalidIntervalFormat('You need to use strings');
    }

    public function toDateInterval()
    {
        return new DateInterval("PT{$this->seconds()}S");
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
                $callable = 'Timeless\\' . $tokenToFunction[$matches['unit']];
                if (is_callable($callable)) {
                    return call_user_func($callable, $matches['quantity']);
                }

                throw new \RuntimeException("function `$callable` does not exists");
            }
            if (!preg_match('/^\d+$/', $string)) {
                throw new InvalidIntervalFormat("'{$string}' is not a valid Interval format");
            }
        }
        if (is_numeric($string)) {
            $duration = floor(floatval($string));
            throw new InvalidIntervalFormat("Maybe you mean '{$duration} seconds' or something like that?");
        }
        throw new InvalidIntervalFormat('You need to use strings');
    }

    public static function fromDateInterval(DateInterval $interval)
    {
        $startTime = new DateTimeImmutable();
        $endTime = $startTime->add($interval);
        return new self(($endTime->getTimestamp() - $startTime->getTimestamp()) * 1000);
    }
}
