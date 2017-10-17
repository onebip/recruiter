<?php
namespace Recruiter\Workable;

use Exception;
use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class FailsInConstructor implements Workable
{
    use WorkableBehaviour;

    public function __construct($parameters = [], $fromRecruiter = true)
    {
        if ($fromRecruiter) {
            throw new Exception("I am supposed to fail in constructor code for testing purpose");
        }
    }
}
