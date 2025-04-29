<?php

// tests the configuration for "dibi"

require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Bootstrap\Configurator;
$configurator->setTempDirectory(__DIR__ . '/../temp');
$configurator->addConfig(__DIR__ . '/../config/common.neon');
$configurator->addConfig(__DIR__ . '/../config/local.neon');
$configurator->setDebugMode(true);  
$configurator->enableTracy(__DIR__.'/../log');
$container = $configurator->createContainer();

$dibi = $container->getByType(Dibi\Connection::class);
$result = $dibi->query('SELECT 1 AS test');
echo $result->fetchSingle() ? 'Dibi connection by type successful!' : 'Dibi connection by type failed.';

echo "<br>";

$dibi = $container->getService('dibi.connection');
$result = $dibi->query('SELECT 1 AS test');
echo $result->fetchSingle() ? 'Dibi connection by service successful!' : 'Dibi connection by service failed.';
