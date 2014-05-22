<?php

namespace Recruiter\Jobs;

class Locked
{
    private $ids;
    private $scheduled;

    public function __construct($ids, $scheduled)
    {
        $this->ids = $ids;
        $this->scheduled = $scheduled;
    }

    public function assignTo($unit)
    {
        return $unit->combineWith($this->ids);
    }
}
