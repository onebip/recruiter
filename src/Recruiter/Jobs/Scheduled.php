<?php

namespace Recruiter\Jobs;

use MongoCollection;
use MongoDate;

class Scheduled
{
    private $scheduled;

    public function __construct(MongoCollection $scheduled)
    {
        $this->scheduled = $scheduled;
    }

    public function pick($bySkill, $howMany)
    {
        return Ready::from(
            $this->scheduled
                ->find([
                    'scheduled_at' => ['$lt' => new MongoDate()],
                    'active' => true,
                    'locked' => false
                ], [
                    '_id' => true
                ])
                ->sort(['scheduled_at' => 1])
                // XXX:
                // if you limit at 0 then all results
                // are returned so we are limiting for 1
                // to have something to do iterator_to_array on
                ->limit(max(1, $howMany)),
            $this->scheduled
        );
    }
}
