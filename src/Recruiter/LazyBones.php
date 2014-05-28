<?php

namespace Recruiter;

class LazyBones implements Workable
{
    use Recruitable;

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

    public function __construct($usToSleep, $usOfDelta)
    {
        $this->usToSleep = $usToSleep;
        $this->usOfDelta = $usOfDelta;
    }

    public function execute()
    {
        usleep($this->usToSleep + (rand(-$this->usOfDelta, $this->usOfDelta)));
    }

    public function export()
    {
        return [
            'us_to_sleep' => $this->usToSleep,
            'us_of_delta' => $this->usOfDelta,
        ];
    }

    public static function import($parameters, RetryPolicy $scheduler)
    {
        return new self(
            $parameters['us_to_sleep'],
            $parameters['us_of_delta']
        );
    }
}
