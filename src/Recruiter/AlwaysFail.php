<?php

namespace Recruiter;

class AlwaysFail implements Workable
{
    use Recruitable;

    public function execute()
    {
        throw new \Exception('Sorry, I\'m good for nothing');
    }
}
