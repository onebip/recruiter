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
                ->limit($howMany),
            $this->scheduled
        );
    }
}
