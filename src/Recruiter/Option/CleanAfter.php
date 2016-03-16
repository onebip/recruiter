<?php

namespace Recruiter\Option;

use Recruiter;
use Ulrichsg\Getopt;
use Timeless as T;
use UnexpectedValueException;

class CleanAfter implements Recruiter\Option
{
    private $name;
    private $default;

    public function __construct($name, $default)
    {
        $this->name = $name;
        $this->default = T\Interval::parse($default);
    }

    public function specification()
    {
        return (new Getopt\Option(null, $this->name, Getopt\Getopt::REQUIRED_ARGUMENT))
            ->setDescription(
                sprintf(
                    'Upper limit of time to wait before deleting executed jobs from archived collection [%s]',
                    $this->default->format('s')
                )
            );
    }

    public function pickFrom(GetOpt\GetOpt $optionsFromCommandLine)
    {
        if ($option = $optionsFromCommandLine->getOption($this->name)) {
            return T\Interval::parse($option);
        }
        return $this->default;
    }
}
