<?php
namespace Recruiter;

use Exception;
use Recruiter\Workable\RecoverFromException;

class WorkableInJob
{
    public static function import($document)
    {
        try {
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
        } catch (Exception $e) {
            return new RecoverFromException($dataAboutWorkableObject['parameters'], $dataAboutWorkableObject['class'], $e);
        }
    }

    public static function export($workable, $methodToCall)
    {
        return [
            'workable' => [
                'class' => self::classNameOf($workable),
                'parameters' => $workable->export(),
                'method' => $methodToCall,
            ]
        ];
    }

    public static function initialize()
    {
        return ['workable' => ['method' => 'execute']];
    }

    private static function classNameOf($workable)
    {
        $workableClassName = get_class($workable);
        if (method_exists($workable, 'getClass')) {
            $workableClassName = $workable->getClass();
        }
        return $workableClassName;
    }
}
