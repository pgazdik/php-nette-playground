<?php

// tests the configuration for "database"

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Bootstrap\Configurator;
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->addConfig(__DIR__ . '/../config/common.neon');
$configurator->addConfig(__DIR__ . '/../config/local.neon');
$configurator->setDebugMode(true);
$configurator->enableTracy(__DIR__.'/../log');
$container = $configurator->createContainer();

$db = $container->getByType(Nette\Database\Connection::class);

$result = $db->query('SELECT 1 AS test');
echo $result->fetchField('test') ? 'Database connection successful!' : 'Database connection failed.';