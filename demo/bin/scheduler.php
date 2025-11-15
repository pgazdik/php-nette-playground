#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

# This is called by cron (running in the php-fpm container) every minute and eventually calls the 'console' script.

$bootstrap = new App\Bootstrap;
$container = $bootstrap->bootWebApplication();

use Orisai\Scheduler\Scheduler;
$scheduler = $container->getByType(Scheduler::class);
$scheduler->run();