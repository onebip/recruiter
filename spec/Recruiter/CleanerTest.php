<?php

namespace Recruiter;

class CleanerTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldCreateCleaner()
    {
        $this->cleaner = new Cleaner();
        $this->assertNotNull($this->cleaner);
    }
}