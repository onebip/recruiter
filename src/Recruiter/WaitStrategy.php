<?php

namespace Recruiter;

use Timeless\Duration;

class WaitStrategy
{
    private $timeToWaitAtLeast;
    private $timeToWaitAtMost;
    private $timeToWait;
    private $howToWait;

    public function __construct(Duration $timeToWaitAtLeast, Duration $timeToWaitAtMost, $howToWait = 'usleep')
    {
        $this->timeToWaitAtLeast = $timeToWaitAtLeast->milliseconds();
        $this->timeToWaitAtMost = $timeToWaitAtMost->milliseconds();
        $this->timeToWait = $timeToWaitAtLeast->milliseconds();
        $this->howToWait = $howToWait;
    }

    public function reset()
    {
        $this->timeToWait = $this->timeToWaitAtLeast;
        return $this;
    }

    public function goForward()
    {
        $this->timeToWait =  max(
            $this->timeToWait / 2,
            $this->timeToWaitAtLeast
        );
        return $this;
    }

    public function backOff()
    {
        $this->timeToWait = min(
            $this->timeToWait * 2,
            $this->timeToWaitAtMost
        );
        return $this;
    }

    public function wait()
    {
        call_user_func($this->howToWait, $this->timeToWait * 1000);
        return $this;
    }
}
