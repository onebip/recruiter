<?php

namespace Timeless;

class DurationFormatTest extends \PHPUnit_Framework_TestCase
{
    public function testFormatExtended()
    {
        $this->assertEquals('4 milliseconds', milliseconds(4)->format('milliseconds'));
        $this->assertEquals('1 second', milliseconds(1000)->format('seconds'));
        $this->assertEquals('4 seconds', seconds(4)->format('seconds'));
        $this->assertEquals('4 minutes', minutes(4)->format('minutes'));
        $this->assertEquals('32 hours', hours(32)->format('hours'));
        $this->assertEquals('4 days', days(4)->format('days'));
        $this->assertEquals('1 week', days(7)->format('weeks'));
        $this->assertEquals('3 months', weeks(13)->format('months'));
        $this->assertEquals('1 year', months(12)->format('years'));
    }

    public function testFormatShort()
    {
        $this->assertEquals('4ms', milliseconds(4)->format('ms'));
        $this->assertEquals('1s', milliseconds(1000)->format('s'));
        $this->assertEquals('4s', seconds(4)->format('s'));
        $this->assertEquals('4m', minutes(4)->format('m'));
        $this->assertEquals('32h', hours(32)->format('h'));
        $this->assertEquals('4d', days(4)->format('d'));
        $this->assertEquals('1w', days(7)->format('w'));
        $this->assertEquals('3mo', weeks(13)->format('mo'));
        $this->assertEquals('1y', months(12)->format('y'));
    }
}
