<?php

namespace Recruiter;

use Functional as _;
use Recruiter\Option;
use Ulrichsg\Getopt;

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
                    _\map($this->options, function($option) {
                        return $option->specification();
                    })
                )
            );
        $optionsFromCommandLine->parse();
        if ($this->helpHasBeenAsked($optionsFromCommandLine)) {
            $this->showHelpAndExit($optionsFromCommandLine);
        }
        foreach ($this->options as $key => $option) {
            $this->values[$key] = $option->pickFrom($optionsFromCommandLine);
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

    private function showHelpAndExit($optionsFromCommandLine)
    {
        echo $optionsFromCommandLine->getHelpText();
        exit(0);
    }
}
