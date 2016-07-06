<?php

namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class AlwaysSucceed implements Workable
{
    use WorkableBehaviour;

    public function execute()
    {
        // It's easy to do nothing right :-)
    }
}
