<?php

namespace Recruiter;

class LazyBones implements Doable
{
    use Recruitable;

    private $musToSleep;

    public static function waitFor($timeInSeconds)
    {
        return new self($timeInSeconds * 1000 * 1000);
    }

    public static function waitForMs($timeInMs)
    {
        return new self($timeInMs * 1000);
    }

    public function __construct($musToSleep)
    {
        $this->musToSleep = $musToSleep;
    }

    public function execute()
    {
        usleep($this->musToSleep);
    }

    public function export()
    {
        return ['mus_to_sleep' => $this->musToSleep];
    }

    public static function import($parameters)
    {
        return new self($parameters['mus_to_sleep']);
    }
}
