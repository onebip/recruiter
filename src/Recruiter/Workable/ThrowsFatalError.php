<?php
namespace Recruiter\Workable;

use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class ThrowsFatalError implements Workable
{
    use WorkableBehaviour;

    public function execute()
    {
        new ThisClassDoesnNotExists();
    }
}
