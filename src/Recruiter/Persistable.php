<?php

namespace Recruiter;

interface Persistable
{
    public function export();
    public static function import($parameters);
}
