#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Recruiter\Recruiter;
use Recruiter\Factory;
use Recruiter\Workable\LazyBones;
use Recruiter\Worker;
use Recruiter\Option\MemoryLimit;

$factory = new Factory();
$db = $factory->getMongoDb(
    $hosts = 'localhost:27017',
    $options = [],
    $dbName = 'recruiter'
);
$db->drop();

$recruiter = new Recruiter($db);

LazyBones::waitForMs(200, 100)
    ->asJobOf($recruiter)
    ->inGroup('mail')
    ->inBackground()
    ->execute();

$memoryLimit = new MemoryLimit('memory-limit', '64MB');
$worker = $recruiter->hire($memoryLimit);
$worker->workOnJobsGroupedAs('mail');
$assignments = $recruiter->assignJobsToWorkers();
$worker->work();
