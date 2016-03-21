<?php
namespace Recruiter\Acceptance;

use Recruiter\Workable\AlwaysFail;
use Symfony\Component\EventDispatcher\Event;

class HooksTest extends BaseAcceptanceTest
{
    public function testConfiguredHooksAreFiredDuringJobExecution()
    {
        $this->events = [];
        $this->recruiter->getEventDispatcher()->addListener('job.failure.last', function(Event $event) {
            $this->events[] = $event;
        });

        $job = (new AlwaysFail())
            ->asJobOf($this->recruiter)
            ->inBackground()
            ->execute();

        $worker = $this->recruiter->hire();
        $this->recruiter->assignJobsToWorkers();
        $worker->work();

        $this->assertEquals(1, count($this->events));
        $this->assertInstanceOf('Recruiter\Job\Event', $this->events[0]);
        $this->assertEquals('not-scheduled-by-retry-policy', $this->events[0]->export()['why']);
    }
}
