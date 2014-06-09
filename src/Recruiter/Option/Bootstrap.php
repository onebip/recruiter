<?php

namespace Recruiter\Option;

use Recruiter;
use Ulrichsg\Getopt;
use UnexpectedValueException;

class Bootstrap implements Recruiter\Option
{
    private $name;
    private $bootstrapFilePath;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function specification()
    {
        return (new Getopt\Option(null, $this->name, Getopt\Getopt::REQUIRED_ARGUMENT))
            ->setDescription('A PHP file that loads the worker environment');
    }

    public function pickFrom(GetOpt\GetOpt $optionsFromCommandLine) {
        $this->bootstrapFilePath = $this->validate(
            $optionsFromCommandLine->getOption($this->name)
        );
        return $this;
    }

    public function load()
    {
        return require $this->bootstrapFilePath;
    }

    private function validate($filePath)
    {
        $this->ensureIsGiven($filePath);
        if (!file_exists($filePath)) {
            $this->throwBecauseFile($filePath, "doesn't exists");
        }
        if (!is_readable($filePath)) {
            $this->throwBecauseFile($filePath, "is not readable");
        }
        return $filePath;
    }

    private function ensureIsGiven($filePath)
    {
        if (is_null($filePath)) {
            throw new UnexpectedValueException(
                sprintf("Option '%s' is required", $this->name)
            );
        }
    }

    private function throwBecauseFile($filePath, $reason)
    {
        throw new UnexpectedValueException(
            sprintf(
                "Option '%s' has an invalid value: file '%s' %s",
                $this->name, $filePath, $reason
            )
        );
    }
}
