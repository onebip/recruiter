<?php
namespace Timeless;

use MongoDB\BSON\UTCDateTime as MongoUTCDateTime;

class MongoDate
{
    public static function from(Moment $moment): MongoUTCDateTime
    {
        $seconds = intval(floor($moment->ms() / 1000));
        $milliseconds = intval($moment->ms() - $seconds * 1000);
        $microseconds = $milliseconds * 1000;

        return new MongoUTCDateTime(
            intval($seconds * 1000 + $milliseconds)
        );
    }

    public static function toMoment(MongoUTCDateTime $mongoDate): Moment
    {
        return new Moment(intval($mongoDate->__toString()));
    }

    public static function now()
    {
        return self::from(now());
    }
}
