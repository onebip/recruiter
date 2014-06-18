<?php

namespace Recruiter;

abstract class BaseWorkable implements Workable
{
    use WorkableBehaviour;

    public function execute() {
        throw new \Exception('Workable::execute() need to be implemented');
    }
}
