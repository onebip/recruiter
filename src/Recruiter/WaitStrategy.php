<?php

namespace Recruiter;

use Timeless\Duration;

class WaitStrategy
{
    private $timeToWaitAtLeast;
    private $timeToWaitAtMost;

    public function __construct(Duration $timeToWaitAtLeast, Duration $timeToWaitAtMost)
    {
        $this->timeToWaitAtLeast = $timeToWaitAtLeast;
        $this->timeToWaitAtMost = $timeToWaitAtMost;
    }
}
