<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$bootstrap = new App\Bootstrap;
$container = $bootstrap->bootApplication();
$application = $container->getByType(Nette\Application\Application::class);
$application->run();

//$x->foo();