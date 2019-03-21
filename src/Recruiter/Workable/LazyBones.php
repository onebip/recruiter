<?php

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class LazyBones implements Workable
{
    use WorkableBehaviour;

    private $usToSleep;
    private $usOfDelta;

    public static function waitFor($timeInSeconds, $deltaInSeconds = 0)
    {
        return new self($timeInSeconds * 1000000, $deltaInSeconds * 1000000);
    }

    public static function waitForMs($timeInMs, $deltaInMs = 0)
    {
        return new self($timeInMs * 1000, $deltaInMs * 1000);
    }

    public function __construct($usToSleep = 1, $usOfDelta = 0)
    {
        $this->usToSleep = $usToSleep;
        $this->usOfDelta = $usOfDelta;
    }

    public function execute()
    {
        usleep($this->usToSleep + (rand(intval(-$this->usOfDelta), $this->usOfDelta)));
    }

    public function export()
    {
        return [
            'us_to_sleep' => $this->usToSleep,
            'us_of_delta' => $this->usOfDelta,
        ];
    }

    public static function import($parameters)
    {
        return new self(
            $parameters['us_to_sleep'],
            $parameters['us_of_delta']
        );
    }
}
