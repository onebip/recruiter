<?php

namespace Recruiter;

interface Doable extends Persistable
{
    public function asJobOf(Recruiter $recruiter);
}
