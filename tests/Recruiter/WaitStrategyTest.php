<?php

namespace Recruiter;

use Timeless as T;

class WaitStrategyTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->waited = 0;
        $this->howToWait = function($microseconds) {
            $this->waited = $microseconds;
        };
        $this->timeToWaitAtLeast = T\milliseconds(250);
        $this->timeToWaitAtMost = T\seconds(30);
    }

    public function testStartsToWaitTheMinimumAmountOfTime()
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait
        );
        $ws->wait();
        $this->assertEquals(250000, $this->waited);
    }

    public function testBackingOffIncreasesTheIntervalExponentially()
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait
        );
        $ws->wait();
        $this->assertEquals(250000, $this->waited);
        $ws->backOff()->wait();
        $this->assertEquals(500000, $this->waited);
        $ws->backOff()->wait();
        $this->assertEquals(1000000, $this->waited);
    }

    public function testBackingOffCannotIncreaseTheIntervalOverAMaximum()
    {
        $ws = new WaitStrategy(T\seconds(1), T\seconds(2), $this->howToWait);
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->wait();
        $this->assertEquals(2000000, $this->waited);
    }

    public function testGoingForwardLowersTheSleepingPeriod()
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait
        );
        $ws->backOff();
        $ws->goForward();
        $ws->wait();
        $this->assertEquals(250000, $this->waited);
    }

    public function testGoingForwardCannotLowerTheIntervalBelowMinimum()
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait
        );
        $ws->goForward();
        $ws->goForward();
        $ws->goForward();
        $ws->goForward();
        $ws->wait();
        $this->assertEquals(250000, $this->waited);
    }
}
