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

    public function ensure($used = null)
    {
        $used = ByteUnits\box($used ?: memory_get_usage());
        if ($used->isGreaterThan($this->limit)) {
            fprintf(STDERR,
                'Memory limit reached, %s is more than the force limit of %s' . PHP_EOL,
                $used->format(), $this->limit->format()
            );
            exit(1);
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
