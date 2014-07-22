<?php

namespace Recruiter;

class ProcessTable
{
    public function isAlive($pid)
    {
        return posix_kill($pid, 0);
    }
}
