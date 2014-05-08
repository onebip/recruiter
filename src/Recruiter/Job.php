<?php

namespace Recruiter;

use MongoId;
use MongoDate;
use MongoInt32;

class Job
{
    private $toDo;
    private $recruiter;
    private $instantiatedAt;
    private $status;

    public static function around(Workable $toDo, Recruiter $recruiter)
    {
        $baseDocumentForJob = [
            'active' => true,
            'done' => false,
            'created_at' => new MongoDate(),
            'attempts' => 0,
            'method' => 'execute',
            'class' => get_class($toDo),
            'locked' => false,
            'tags' => []
        ];

        return new self($baseDocumentForJob, $toDo, $recruiter);
    }

    public function __construct($status, Workable $toDo, Recruiter $recruiter)
    {
        $this->toDo = $toDo;
        $this->status = $status;
        $this->recruiter = $recruiter;
        $this->instantiatedAt = new MongoDate();
    }

    public function scheduleTo()
    {
        $this->status['scheduled_at'] = new MongoDate();
        return $this;
    }

    public function execute()
    {
        if ($this->isSheduledLater()) {
            return $this->recruiter->schedule($this);
        }
        $this->toDo->execute();
    }

    public function export()
    {
        return array_merge(
            $this->status,
            ['attempts' => new MongoInt32($this->status['attempts'])]
        );
    }

    public function isActive()
    {
        return array_key_exists('active', $this->status) && $this->status['active'];
    }

    private function isSheduledLater()
    {
        return array_key_exists('scheduled_at', $this->status) &&
            ($this->instantiatedAt->sec <= $this->status['scheduled_at']->sec) &&
            ($this->instantiatedAt->usec <= $this->status['scheduled_at']->usec);
    }
}
