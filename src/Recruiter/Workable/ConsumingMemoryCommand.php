<?php
namespace Recruiter\Workable;

use Exception;
use Recruiter\Workable;
use Recruiter\WorkableBehaviour;

class ConsumingMemoryCommand implements Workable
{
    use WorkableBehaviour;

    public function execute()
    {
        if ($this->parameters['withMemoryLeak']) {
            global $occupied;
        }

        $occupied = new \SplFixedArray($this->parameters['howManyItems']);
    }
}

