<?php

namespace Recruiter\Workers;

use Functional as _;

class AvailableToWork
{
    private $idsAndSkills;
    private $roster;

    public static function from($cursor, $roster)
    {
        return new self(iterator_to_array($cursor), $roster);
    }

    public function __construct($idsAndSkills, $roster)
    {
        $this->idsAndSkills = $idsAndSkills;
        $this->roster = $roster;
    }

    public function count()
    {
        return count($this->idsAndSkills);
    }

    public function groupByWhatTheyCanDo()
    {
        return [
            new Unit(_\pluck($this->idsAndSkills, '_id'), $this->roster)
        ];
    }
}
