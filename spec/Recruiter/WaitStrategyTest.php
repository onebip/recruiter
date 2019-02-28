<?php

namespace Recruiter;

use PHPUnit\Framework\TestCase;
use Timeless as T;

class WaitStrategyTest extends TestCase
{
    public function setUp(): void
    {
        $this->waited = 0;
        $this->howToWait = function($microseconds) {
            $this->waited = T\milliseconds($microseconds/1000);
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
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
    }

    public function testBackingOffIncreasesTheIntervalExponentially()
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait
        );
        $ws->wait();
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
        $ws->backOff()->wait();
        $this->assertEquals($this->timeToWaitAtLeast->multiplyBy(2), $this->waited);
        $ws->backOff()->wait();
        $this->assertEquals($this->timeToWaitAtLeast->multiplyBy(4), $this->waited);
    }

    public function testBackingOffCannotIncreaseTheIntervalOverAMaximum()
    {
        $ws = new WaitStrategy(T\seconds(1), T\seconds(2), $this->howToWait);
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->wait();
        $this->assertEquals(T\seconds(2), $this->waited);
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
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
    }

    public function testTheSleepingPeriodCanBeResetToTheMinimum()
    {
        $ws = new WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->timeToWaitAtMost,
            $this->howToWait
        );
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->backOff();
        $ws->reset();
        $ws->wait();
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
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
        $this->assertEquals($this->timeToWaitAtLeast, $this->waited);
    }
}
