<?php

namespace Recruiter\Option;

use Recruiter;
use Ulrichsg\Getopt;
use UnexpectedValueException;
use ByteUnits;

class MemoryLimit implements Recruiter\Option
{
    private $name;
    private $limit;

    public function __construct($name, $limit)
    {
        $this->name = $name;
        $this->limit = ByteUnits\parse($limit);
    }

    public function specification()
    {
        return (new Getopt\Option(null, $this->name, Getopt\Getopt::REQUIRED_ARGUMENT))
            ->setDescription(
                sprintf(
                    'Maximum amount of memory allocable [%s]',
                    $this->limit->format()
                )
            );
    }

    public function pickFrom(GetOpt\GetOpt $optionsFromCommandLine)
    {
        $this->limit = $this->validate(
            $optionsFromCommandLine->getOption($this->name)
        );
        return $this;
    }

    public function ensure($used)
    {
        $used = ByteUnits\box($used);
        if ($used->isGreaterThan($this->limit)) {
            throw new MemoryLimitExceededException(sprintf(
                'Memory limit reached, %s is more than the force limit of %s',
                $used->format(), $this->limit->format()
            ));
        }
    }

    private function validate($argument)
    {
        if (!is_null($argument)) {
            try {
                return ByteUnits\parse($argument);
            } catch (ByteUnits\ParseException $e) {
                throw new UnexpectedValueException(
                    sprintf("Option '%s' has an invalid value: %s", $this->name, $e->getMessage())
                );
            }
        }
        return $this->limit;
    }
}
