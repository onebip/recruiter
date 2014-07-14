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
        $this->timeToWaitAtLeast = T\milliseconds(200);
        $this->timeToWaitAtMost = $this->validate($default);
    }

    public function specification()
    {
        return (new Getopt\Option(null, $this->name, Getopt\Getopt::REQUIRED_ARGUMENT))
            ->setDescription(
                sprintf(
                    'Upper limit of time to wait before next polling [%s]',
                    $this->timeToWaitAtMost->format('s')
                )
            );
    }

    public function pickFrom(GetOpt\GetOpt $optionsFromCommandLine)
    {
        return new Recruiter\WaitStrategy(
            $this->timeToWaitAtLeast,
            $this->validate(
                $optionsFromCommandLine->getOption($this->name)
            )
        );
    }

    private function validate($argument)
    {
        if (!is_null($argument)) {
            try {
                return T\Interval::parse($argument);
            } catch (T\InvalidIntervalFormat $e) {
                throw new UnexpectedValueException(
                    sprintf("Option '%s' has an invalid value: %s", $this->name, $e->getMessage())
                );
            }
        }
        return $this->timeToWaitAtMost;
    }
}
