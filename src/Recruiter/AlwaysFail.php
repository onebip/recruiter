<?php

namespace Recruiter;

class AlwaysFail implements Workable
{
    use WorkableBehaviour;

    public function execute()
    {
        throw new \Exception('Sorry, I\'m good for nothing');
    }
}
