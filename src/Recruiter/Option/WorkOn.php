<?php

namespace Recruiter\Option;

use Recruiter;
use Ulrichsg\Getopt;
use UnexpectedValueException;

class WorkOn implements Recruiter\Option
{
    private $name;
    private $label;

    public function __construct($name)
    {
        $this->name = $name;
        $this->label = 'all';
    }

    public function specification()
    {
        return (new Getopt\Option(null, $this->name, Getopt\Getopt::REQUIRED_ARGUMENT))
            ->setDescription(
                sprintf('Work only on jobs grouped with this label [%s]', $this->label)
            );
    }

    public function pickFrom(GetOpt\GetOpt $optionsFromCommandLine) {
        $this->label = $this->validate(
            $optionsFromCommandLine->getOption($this->name)
        );
        return $this;
    }

    public function applyTo(Recruiter\Worker $worker)
    {
        if ($this->label !== 'all') {
            $worker->workOnJobsGroupedAs($this->label);
        }
        return $worker;
    }

    private function validate($label)
    {
        if (is_null($label)) {
            return $this->label;
        }
        if (is_string($label) && !empty($label)) {
            return $label;
        }
        throw new UnexpectedValueException(
            sprintf(
                "Option '%s' has an invalid value: '%s' is not a valid job label",
                $this->name, $label
            )
        );
    }
}
