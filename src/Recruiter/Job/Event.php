<?php
namespace Recruiter\Job;

use Symfony\Component\EventDispatcher;

class Event extends EventDispatcher\Event
{
    private $jobExport;

    public function __construct(array $jobExport)
    {
        $this->jobExport = $jobExport;
    }

    public function export()
    {
        return $this->jobExport;
    }

    public function hasTag($wantedTag)
    {
        $tags = array_key_exists('tags', $this->jobExport) ? $this->jobExport['tags'] : [];
        return in_array($wantedTag, $tags);
    }
}
