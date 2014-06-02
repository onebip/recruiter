<?php

namespace Recruiter;

trait Retriable
{
    private $parameters;

    public function __construct($parameters = [])
    {
        $this->parameters = $parameters;
    }

    public function export()
    {
        return $this->parameters;
    }

    public static function import($parameters)
    {
        return new self($parameters);
    }
}
