<?php

use Tracy\Debugger;


require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Bootstrap\Configurator;
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->addConfig(__DIR__ . '/../config/common.neon');
$configurator->addConfig(__DIR__ . '/../config/local.neon');
$configurator->setDebugMode(true);
$configurator->enableTracy(__DIR__.'/../log');

error_log("Simple log 1 from test-logging.php");
error_log("Simple log 2 from test-logging.php");

# see log/info.log
Debugger::log("Tracy info log");

# see log/error.log
Debugger::log("Tracy error log", Debugger::ERROR);

echo "See console and log files";