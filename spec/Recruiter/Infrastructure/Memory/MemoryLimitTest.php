<?php
declare(strict_types=1);

namespace Recruiter\Infrastructure\Memory;

use PHPUnit\Framework\TestCase;

class MemoryLimitTest extends TestCase
{
    public function testThrowsAnExceptionWhenMemoryLimitIsExceeded()
    {
        $this->expectException(MemoryLimitExceededException::class);
        $memoryLimit = new MemoryLimit(1);
        $memoryLimit->ensure(2);
    }
}
