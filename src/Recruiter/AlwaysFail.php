<?php

namespace Recruiter;

class AlwaysFail implements Workable
{
    use Recruitable;

    public function __construct($parameters = [])
    {
        $this->parameters = $parameters;
    }

    public function execute()
    {
        throw new \Exception("Sorry, I'm good for nothing");
    }
}
