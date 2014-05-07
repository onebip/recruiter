<?php

namespace Recruiter;

trait Recruitable
{
    public function asJobOf(Recruiter $recruiter)
    {
        return $recruiter->jobOf($this);
    }
}
