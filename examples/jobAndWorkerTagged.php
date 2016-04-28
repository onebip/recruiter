#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Recruiter\Recruiter;
use Recruiter\Workable\LazyBones;
use Recruiter\Worker;

$db = (new MongoClient())->selectDB('recruiter');
$db->drop();

$recruiter = new Recruiter($db);

LazyBones::waitForMs(200, 100)
    ->asJobOf($recruiter)
    ->inGroup('mail')
    ->inBackground()
    ->execute();

$worker = $recruiter->hire();
$worker->workOnJobsGroupedAs('mail');
$assignments = $recruiter->assignJobsToWorkers();
$worker->work();
