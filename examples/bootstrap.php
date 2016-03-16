<?php

echo 'BOOTSTRAP!!!' . PHP_EOL;
$recruiter->getEventDispatcher()->addListener('job.failure.last', function($event) {
    error_log("Job definitively failed: " . var_export($event->export(), true));
});
