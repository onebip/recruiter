<?php

namespace Recruiter\Option;

use Recruiter;
use Ulrichsg\Getopt;
use Timeless as T;
use UnexpectedValueException;

class WaitStrategy implements Recruiter\Option
{
    private $name;
    private $timeToWaitAtLeast;
    private $timeToWaitAtMost;

    public function __construct($name, $default)
    {
        $this->name = $name;
        $this->validate($default,
            function(T\Duration $timeToWaitAtMost) {
                $this->timeToWaitAtLeast = T\milliseconds(200);
                $this->timeToWaitAtMost = $timeToWaitAtMost;
            }
        );
    }

    public function specification()
    {
        return (new Getopt\Option(null, $this->name, Getopt\Getopt::REQUIRED_ARGUMENT))
            ->setDescription(
                sprintf(
                    'Upper limit of time to wait before next polling [%s]',
                    // TODO: $this->timeToWaitAtMost->format('s')
                    $this->timeToWaitAtMost->seconds() . 's'
                )
            );
    }

    public function pickFrom(GetOpt\GetOpt $optionsFromCommandLine) {
        $argument = $optionsFromCommandLine->getOption($this->name);
        return $this->validate($argument,
            function(T\Duration $timeToWaitAtMost) {
                return new Recruiter\WaitStrategy(
                    $this->timeToWaitAtLeast, $timeToWaitAtMost
                );
            }
        );
    }

    private function validate($argument, $callback)
    {
        if (!is_null($argument)) {
            try {
                return $callback(T\Duration::parse($argument));
            } catch (T\InvalidDurationFormat $e) {
                throw new UnexpectedValueException(
                    sprintf("Option '%s' has an invalid value: %s", $this->name, $e->getMessage())
                );
            }
        }
        return $callback($this->timeToWaitAtMost);
    }
}
