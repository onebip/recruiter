<?php

namespace Recruiter;

use Ulrichsg\Getopt\GetOpt;

interface Option
{
    public function specification();
    public function pickFrom(GetOpt $optionsInCommandLine);
}
