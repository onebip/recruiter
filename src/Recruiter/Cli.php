<?php

namespace Recruiter;

use Recruiter\Option;
use Ulrichsg\Getopt;
use UnexpectedValueException;
use Underscore\Underscore as _;

class Cli
{
    private $options;

    public function __construct()
    {
        $this->options = [];
        $this->values = [];
    }

    public function add($key, Option $option)
    {
        $this->options[$key] = $option;
    }

    public function get($key, $orDefault = null)
    {
        return array_key_exists($key, $this->values) ? $this->values[$key] : $orDefault;
    }

    public function parse($arguments = null)
    {
        $optionsFromCommandLine =
            new Getopt\Getopt(
                $this->addHelpOption(
                    _::transform($this->options, function($option) {
                        return $option->specification();
                    })
                )
            );
        try {
            $optionsFromCommandLine->parse();
            if ($this->helpHasBeenAsked($optionsFromCommandLine)) {
                $this->showHelpAndExitWith($optionsFromCommandLine, 0);
            }
            foreach ($this->options as $key => $option) {
                $this->values[$key] = $option->pickFrom($optionsFromCommandLine);
            }
        } catch(UnexpectedValueException $e) {
            $this->showErrorMessageAndExit($e, $optionsFromCommandLine);
        }
        return $this;
    }

    private function addHelpOption($options)
    {
        $options[] = (new Getopt\Option('h', 'help'))->setDescription('Shows this help');
        return $options;
    }

    private function helpHasBeenAsked($optionsFromCommandLine)
    {
        return !!($optionsFromCommandLine->getOption('help'));
    }

    private function showErrorMessageAndExit($exception, $optionsFromCommandLine)
    {
        printf("\n%s\n\n", $exception->getMessage());
        $this->showHelpAndExitWith($optionsFromCommandLine, 1);
    }

    private function showHelpAndExitWith($optionsFromCommandLine, $status)
    {
        printf("%s", $optionsFromCommandLine->getHelpText());
        exit($status);
    }
}
