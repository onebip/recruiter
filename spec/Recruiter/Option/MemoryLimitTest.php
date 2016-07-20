<?php
namespace Recruiter\Option;

class MemoryLimitTest extends \PHPUnit_Framework_TestCase
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
