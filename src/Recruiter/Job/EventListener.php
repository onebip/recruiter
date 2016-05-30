<?php

namespace Recruiter\Job;

interface EventListener
{
    public function onEvent($channel, Event $ev);
}
