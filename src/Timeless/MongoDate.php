<?php
namespace Timeless;

class MongoDate
{
    public static function from(Moment $moment)
    {
        $seconds = floor($moment->ms() / 1000);
        $milliseconds = $moment->ms() - $seconds * 1000;
        $microseconds = $milliseconds * 1000;
        return new \MongoDate($seconds, $microseconds);
    }

    /**
     * @return Moment
     */
    public static function toMoment(\MongoDate $mongoDate)
    {
        $milliseconds = $mongoDate->sec * 1000 + round($mongoDate->usec / 1000);
        return new Moment($milliseconds);
    }

    public static function now()
    {
        return self::from(now());
    }
}
