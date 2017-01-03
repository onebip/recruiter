<?php

namespace Recruiter;

use Timeless as T;
use Recruiter\Taggable;

class TaggableWorkableTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->repository = $this
                          ->getMockBuilder('Recruiter\Job\Repository')
                          ->disableOriginalConstructor()
                          ->getMock();
    }

    public function testWorkableExportsTags()
    {
        $workable = new WorkableTaggable(['a', 'b']);
        $job = Job::around($workable, $this->repository);

        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['a', 'b'], $exported['tags']);
    }

    public function testCanSetTagsOnJobs()
    {
        $workable = new WorkableTaggable([]);
        $job = Job::around($workable, $this->repository);
        $job->taggedAs(['c']);

        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['c'], $exported['tags']);
    }

    public function testTagsAreMergedTogether()
    {
        $workable = new WorkableTaggable(['a', 'b']);
        $job = Job::around($workable, $this->repository);
        $job->taggedAs(['c']);

        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['a', 'b', 'c'], $exported['tags']);
    }

    public function testTagsAreUnique()
    {
        $workable = new WorkableTaggable(['c']);
        $job = Job::around($workable, $this->repository);
        $job->taggedAs(['c']);

        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['c'], $exported['tags']);
    }

    public function testEmptyTagsAreNotExported()
    {
        $workable = new WorkableTaggable([]);
        $job = Job::around($workable, $this->repository);

        $exported = $job->export();
        $this->assertArrayNotHasKey('tags', $exported);
    }

    public function testTagsAreImported()
    {
        $workable = new WorkableTaggable(['a', 'b']);
        $job = Job::around($workable, $this->repository);
        $job->taggedAs(['c']);

        $exported = $job->export();
        // Here tags will be imported at job level, Workable will
        // import its own tags to be able to respond to `taggedAs`,
        // so ['a', 'b', 'c'] will be imported at the job level and
        $job = Job::import($exported, $this->repository);

        // Here we will merge ['a', 'b', 'c'] at the job level with
        // ['a', 'b'] returned from `Workable::taggedAs`, the result
        // is always the same because tags are kept unique
        $exported = $job->export();
        $this->assertArrayHasKey('tags', $exported);
        $this->assertEquals(['a', 'b', 'c'], $exported['tags']);
    }
}

class WorkableTaggable implements Workable, Taggable
{
    use WorkableBehaviour;

    private $tags;

    public function __construct(array $tags)
    {
        $this->tags = $tags;
    }

    public function taggedAs()
    {
        return $this->tags;
    }

    public function export()
    {
        return ['tags' => $this->tags];
    }

    public static function import($parameters)
    {
        return new self($parameters['tags']);
    }

    public function execute()
    {
        // nothing is good
    }
}
