<?php
declare(strict_types=1);

namespace Recruiter;

use function Onebip\array_some;

/**
 * Class SynchronousExecutionReport
 */
class SynchronousExecutionReport
{
    /**
     * @var array
     */
    private $data;

    /**
     * @param array $data = []
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }


    /**
     *. @params array $data : key value array where key are the id of the job and value is the JobExecution
     */
    public static function fromArray(array $data): SynchronousExecutionReport
    {
        return new self($data);
    }

    public function isThereAFailure()
    {
        return array_some($this->data, function ($jobExecution, $jobId) {
            return $jobExecution->isFailed();
        });
    }

    public function toArray()
    {
        return $this->data;
    }
}
