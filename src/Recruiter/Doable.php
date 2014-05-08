<?php

namespace Recruiter;

interface Workable extends Persistable
{
    public function asJobOf(Recruiter $recruiter);
}
