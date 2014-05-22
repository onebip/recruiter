<?php

namespace Recruiter\Jobs;

use Functional as _;
use MongoCollection;

class Ready
{
    private $ids;
    private $scheduled;

    public static function from($cursor, $repository)
    {
        return new self(
            _\pluck(iterator_to_array($cursor), '_id'),
            $repository
        );
    }

    public function __construct($ids, MongoCollection $scheduled)
    {
        $this->ids = $ids;
        $this->scheduled = $scheduled;
    }

    public function lock()
    {
        $this->scheduled->update(
            ['_id' => ['$in' => $this->ids]],
            ['$set' => ['lock' => true]],
            ['multiple' => true]
        );
        return new Locked($this->ids, $this->scheduled);
    }
}
