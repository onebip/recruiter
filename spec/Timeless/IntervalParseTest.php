<?php

namespace Timeless;

use DateInterval;
use PHPUnit\Framework\TestCase;

class IntervalParseTest extends TestCase
{
    public function testParseExtendedFormat()
    {
        $this->assertEquals(milliseconds(4), Interval::parse('4 milliseconds'));
        $this->assertEquals(milliseconds(4), Interval::parse('4milliseconds'));
        $this->assertEquals(milliseconds(4), Interval::parse('4    milliseconds'));
        $this->assertEquals(milliseconds(4), Interval::parse('+4 milliseconds'));
        $this->assertEquals(milliseconds(4), Interval::parse('4 milliseconds!!!'));
        $this->assertEquals(milliseconds(1), Interval::parse('1 millisecond'));

        $this->assertEquals(seconds(4), Interval::parse('4 seconds'));
        $this->assertEquals(seconds(4), Interval::parse('4seconds'));
        $this->assertEquals(seconds(4), Interval::parse('4    seconds'));
        $this->assertEquals(seconds(4), Interval::parse('+4 seconds'));
        $this->assertEquals(seconds(4), Interval::parse('4 seconds!!!'));
        $this->assertEquals(seconds(1), Interval::parse('1 second'));

        $this->assertEquals(minutes(4), Interval::parse('4 minutes'));
        $this->assertEquals(minutes(4), Interval::parse('4minutes'));
        $this->assertEquals(minutes(4), Interval::parse('4    minutes'));
        $this->assertEquals(minutes(4), Interval::parse('+4 minutes'));
        $this->assertEquals(minutes(4), Interval::parse('4 minutes!!!'));
        $this->assertEquals(minutes(1), Interval::parse('1 minute'));

        $this->assertEquals(hours(4), Interval::parse('4 hours'));
        $this->assertEquals(hours(4), Interval::parse('4hours'));
        $this->assertEquals(hours(4), Interval::parse('4    hours'));
        $this->assertEquals(hours(4), Interval::parse('+4 hours'));
        $this->assertEquals(hours(4), Interval::parse('4 hours!!!'));
        $this->assertEquals(hours(1), Interval::parse('1 hour'));

        $this->assertEquals(days(4), Interval::parse('4 days'));
        $this->assertEquals(days(4), Interval::parse('4days'));
        $this->assertEquals(days(4), Interval::parse('4    days'));
        $this->assertEquals(days(4), Interval::parse('+4 days'));
        $this->assertEquals(days(4), Interval::parse('4 days!!!'));
        $this->assertEquals(days(1), Interval::parse('1 day'));

        $this->assertEquals(weeks(4), Interval::parse('4 weeks'));
        $this->assertEquals(weeks(4), Interval::parse('4weeks'));
        $this->assertEquals(weeks(4), Interval::parse('4    weeks'));
        $this->assertEquals(weeks(4), Interval::parse('+4 weeks'));
        $this->assertEquals(weeks(4), Interval::parse('4 weeks!!!'));
        $this->assertEquals(weeks(1), Interval::parse('1 week'));

        $this->assertEquals(months(4), Interval::parse('4 months'));
        $this->assertEquals(months(4), Interval::parse('4months'));
        $this->assertEquals(months(4), Interval::parse('4    months'));
        $this->assertEquals(months(4), Interval::parse('+4 months'));
        $this->assertEquals(months(4), Interval::parse('4 months!!!'));
        $this->assertEquals(months(1), Interval::parse('1 month'));

        $this->assertEquals(years(4), Interval::parse('4 years'));
        $this->assertEquals(years(4), Interval::parse('4years'));
        $this->assertEquals(years(4), Interval::parse('4    years'));
        $this->assertEquals(years(4), Interval::parse('+4 years'));
        $this->assertEquals(years(4), Interval::parse('4 years!!!'));
        $this->assertEquals(years(1), Interval::parse('1 year'));
    }

    public function testParseShortFormat()
    {
        $this->assertEquals(milliseconds(4), Interval::parse('4 ms'));
        $this->assertEquals(milliseconds(4), Interval::parse('4ms'));
        $this->assertEquals(seconds(4), Interval::parse('4 s'));
        $this->assertEquals(seconds(4), Interval::parse('4s'));
        $this->assertEquals(minutes(4), Interval::parse('4 m'));
        $this->assertEquals(minutes(4), Interval::parse('4m'));
        $this->assertEquals(hours(4), Interval::parse('4 h'));
        $this->assertEquals(hours(4), Interval::parse('4h'));
        $this->assertEquals(days(4), Interval::parse('4 d'));
        $this->assertEquals(days(4), Interval::parse('4d'));
        $this->assertEquals(weeks(4), Interval::parse('4 w'));
        $this->assertEquals(weeks(4), Interval::parse('4w'));
        $this->assertEquals(months(4), Interval::parse('4 mo'));
        $this->assertEquals(months(4), Interval::parse('4mo'));
        $this->assertEquals(years(4), Interval::parse('4 y'));
        $this->assertEquals(years(4), Interval::parse('4y'));
    }

    public function testFromDateInterval()
    {
        $this->assertEquals(days(2), Interval::fromDateInterval(new DateInterval('P2D')));
        $this->assertEquals(minutes(10), Interval::fromDateInterval(new DateInterval('PT10M')));
        $this->assertEquals(days(2)->add(minutes(10)), Interval::fromDateInterval(new DateInterval('P2DT10M')));
    }

    public function testNumberAsIntervalFormat()
    {
        $this->expectException(InvalidIntervalFormat::class);
        $this->expectExceptionMessage("Maybe you mean '5 seconds' or something like that?");
        Interval::parse(5);
    }

    public function testBadString()
    {
        $this->expectException(InvalidIntervalFormat::class);
        Interval::parse('whatever');
    }
}
