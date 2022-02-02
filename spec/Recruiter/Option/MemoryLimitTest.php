<?php
namespace Recruiter\Option;

use PHPUnit\Framework\TestCase;
use Recruiter\Option\MemoryLimitExceededException;

class MemoryLimitTest extends TestCase
{
    public function testThrowsAnExceptionWhenMemoryLimitIsExceeded()
    {
        $this->expectException(MemoryLimitExceededException::class);

        $memoryLimit = new MemoryLimit('test', 1);
        $memoryLimit->ensure(2);
    }
}
