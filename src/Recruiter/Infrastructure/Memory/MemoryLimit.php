<?php
declare(strict_types=1);

namespace Recruiter\Infrastructure\Memory;

use ByteUnits;
use UnexpectedValueException;

/**
 * Class MemoryLimit
 */
class MemoryLimit
{
    private $limit;

    public function __construct($limit)
    {
        try {
            $this->limit = ByteUnits\parse($limit);
        } catch (ByteUnits\ParseException $e) {
            throw new UnexpectedValueException(
                sprintf("Memory limit '%s' is an invalid value: %s", $limit, $e->getMessage())
            );
        }
    }

    public function ensure($used)
    {
        $used = ByteUnits\box($used);
        if ($used->isGreaterThan($this->limit)) {
            throw new MemoryLimitExceededException(sprintf(
                'Memory limit reached, %s is more than the force limit of %s',
                $used->format(),
                $this->limit->format()
            ));
        }
    }
}
