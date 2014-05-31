<?php

namespace Recruiter;

abstract class BaseWorkable implements Workable
{
    use Recruitable;

    public function __construct($parameters = [])
    {
        $this->parameters = $parameters;
    }

    public function execute() {
        throw new \Exception('Workable::execute() need to be implemented');
    }
}
