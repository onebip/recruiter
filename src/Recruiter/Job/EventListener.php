<?php

namespace Recruiter\Job;

interface EventListener
{
    public function onEvent(Event $ev);
}
