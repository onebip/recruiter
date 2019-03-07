#!/usr/bin/env php
<?php
require __DIR__.'/../vendor/autoload.php';

use Geezer\Command\RobustCommandRunner;
use Recruiter\Command\RecruiterCommand;
use Recruiter\Factory;
use Recruiter\Infrastructure\Command\CleanerCommand;
use Recruiter\Infrastructure\Command\WorkerCommand;
use Symfony\Component\Console\Application;

// FIXME:!
$logger = new Psr\Log\NullLogger();

$application = new Application();

$application->add(new RobustCommandRunner(new RecruiterCommand(new Factory()), $logger));
$application->add(new RobustCommandRunner(new WorkerCommand(new Factory()), $logger));
$application->add(new RobustCommandRunner(new CleanerCommand(new Factory()), $logger));

$application->run();
