<?php

namespace Timeless;

class DurationParseTest extends \PHPUnit_Framework_TestCase
{
    public function testParseExtendedFormat()
    {
        $this->assertEquals(milliseconds(4), Duration::parse('4 milliseconds'));
        $this->assertEquals(milliseconds(4), Duration::parse('4milliseconds'));
        $this->assertEquals(milliseconds(4), Duration::parse('4    milliseconds'));
        $this->assertEquals(milliseconds(4), Duration::parse('+4 milliseconds'));
        $this->assertEquals(milliseconds(4), Duration::parse('4 milliseconds!!!'));
        $this->assertEquals(milliseconds(1), Duration::parse('1 millisecond'));

        $this->assertEquals(seconds(4), Duration::parse('4 seconds'));
        $this->assertEquals(seconds(4), Duration::parse('4seconds'));
        $this->assertEquals(seconds(4), Duration::parse('4    seconds'));
        $this->assertEquals(seconds(4), Duration::parse('+4 seconds'));
        $this->assertEquals(seconds(4), Duration::parse('4 seconds!!!'));
        $this->assertEquals(seconds(1), Duration::parse('1 second'));

        $this->assertEquals(minutes(4), Duration::parse('4 minutes'));
        $this->assertEquals(minutes(4), Duration::parse('4minutes'));
        $this->assertEquals(minutes(4), Duration::parse('4    minutes'));
        $this->assertEquals(minutes(4), Duration::parse('+4 minutes'));
        $this->assertEquals(minutes(4), Duration::parse('4 minutes!!!'));
        $this->assertEquals(minutes(1), Duration::parse('1 minute'));

        $this->assertEquals(hours(4), Duration::parse('4 hours'));
        $this->assertEquals(hours(4), Duration::parse('4hours'));
        $this->assertEquals(hours(4), Duration::parse('4    hours'));
        $this->assertEquals(hours(4), Duration::parse('+4 hours'));
        $this->assertEquals(hours(4), Duration::parse('4 hours!!!'));
        $this->assertEquals(hours(1), Duration::parse('1 hour'));

        $this->assertEquals(days(4), Duration::parse('4 days'));
        $this->assertEquals(days(4), Duration::parse('4days'));
        $this->assertEquals(days(4), Duration::parse('4    days'));
        $this->assertEquals(days(4), Duration::parse('+4 days'));
        $this->assertEquals(days(4), Duration::parse('4 days!!!'));
        $this->assertEquals(days(1), Duration::parse('1 day'));

        $this->assertEquals(weeks(4), Duration::parse('4 weeks'));
        $this->assertEquals(weeks(4), Duration::parse('4weeks'));
        $this->assertEquals(weeks(4), Duration::parse('4    weeks'));
        $this->assertEquals(weeks(4), Duration::parse('+4 weeks'));
        $this->assertEquals(weeks(4), Duration::parse('4 weeks!!!'));
        $this->assertEquals(weeks(1), Duration::parse('1 week'));

        $this->assertEquals(months(4), Duration::parse('4 months'));
        $this->assertEquals(months(4), Duration::parse('4months'));
        $this->assertEquals(months(4), Duration::parse('4    months'));
        $this->assertEquals(months(4), Duration::parse('+4 months'));
        $this->assertEquals(months(4), Duration::parse('4 months!!!'));
        $this->assertEquals(months(1), Duration::parse('1 month'));

        $this->assertEquals(years(4), Duration::parse('4 years'));
        $this->assertEquals(years(4), Duration::parse('4years'));
        $this->assertEquals(years(4), Duration::parse('4    years'));
        $this->assertEquals(years(4), Duration::parse('+4 years'));
        $this->assertEquals(years(4), Duration::parse('4 years!!!'));
        $this->assertEquals(years(1), Duration::parse('1 year'));
    }

    public function testParseShortFormat()
    {
        $this->assertEquals(milliseconds(4), Duration::parse('4 ms'));
        $this->assertEquals(milliseconds(4), Duration::parse('4ms'));
        $this->assertEquals(seconds(4), Duration::parse('4 s'));
        $this->assertEquals(seconds(4), Duration::parse('4s'));
        $this->assertEquals(minutes(4), Duration::parse('4 m'));
        $this->assertEquals(minutes(4), Duration::parse('4m'));
        $this->assertEquals(hours(4), Duration::parse('4 h'));
        $this->assertEquals(hours(4), Duration::parse('4h'));
        $this->assertEquals(days(4), Duration::parse('4 d'));
        $this->assertEquals(days(4), Duration::parse('4d'));
        $this->assertEquals(weeks(4), Duration::parse('4 w'));
        $this->assertEquals(weeks(4), Duration::parse('4w'));
        $this->assertEquals(months(4), Duration::parse('4 mo'));
        $this->assertEquals(months(4), Duration::parse('4mo'));
        $this->assertEquals(years(4), Duration::parse('4 y'));
        $this->assertEquals(years(4), Duration::parse('4y'));
    }

    /**
     * @expectedException Timeless\InvalidDurationFormat
     * @expectedExceptionMessage Maybe you mean '5 seconds' or something like that?
     */
    public function testNumberAsDurationFormat()
    {
        Duration::parse(5);
    }

    /**
     * @expectedException Timeless\InvalidDurationFormat
     */
    public function testBadString()
    {
        Duration::parse('whatever');
    }
}
