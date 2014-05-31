#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Recruiter\Recruiter;
use Recruiter\LazyBones;
use Recruiter\RetryPolicy;
use Recruiter\Worker;

$db = (new MongoClient())->selectDB('recruiter');
$db->drop();

$recruiter = new Recruiter($db);

LazyBones::waitForMs(200, 100)
    ->asJobOf($recruiter)
    ->inBackground()
    ->execute();

$worker = $recruiter->hire();
$assignments = $recruiter->assignJobsToWorkers();
$worker->work();
