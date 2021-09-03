<?php
namespace Recruiter\Option;

use PHPUnit\Framework\TestCase;

class MemoryLimitTest extends TestCase
{
    /**
     * @expectedException Recruiter\Option\MemoryLimitExceededException
     */
    public function testThrowsAnExceptionWhenMemoryLimitIsExceeded()
    {
        $memoryLimit = new MemoryLimit('test', 1);
        $memoryLimit->ensure(2);
    }
}
