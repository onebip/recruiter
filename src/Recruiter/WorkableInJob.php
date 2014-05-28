<?php

namespace Recruiter;

class WorkableInJob
{
    public function import($document)
    {
        if (!array_key_exists('workable', $document)) {
            throw new Exception('Unable to import Job without data about Workable object');
        }
        $dataAboutWorkableObject = $document['workable'];
        if (!array_key_exists('class', $dataAboutWorkableObject)) {
            throw new Exception('Unable to import Job without a class');
        }
        if (!class_exists($dataAboutWorkableObject['class'])) {
            throw new Exception('Unable to import Job with unknown Workable class');
        }
        if (!method_exists($dataAboutWorkableObject['class'], 'import')) {
            throw new Exception('Unable to import Workable without method import');
        }
        return $dataAboutWorkableObject['class']::import($dataAboutWorkableObject['parameters']);
    }

    public function export($workable)
    {
        return [
            'class' => get_class($workable),
            'parameters' => $workable->export(),
            'method' => 'execute',
        ];
    }

    public function initialize()
    {
        return [
            'method' => 'execute',
        ];
    }
}
