<?php
namespace Tests\Db;

use Nette\Database\Explorer;
use Nette\DI\Container;
use PHPUnit\Framework\TestCase;
use Tests\TestBootstrap;
use Tracy\Debugger;

// To run tests:
// docker compose exec -w //application/demo php-fpm ./vendor/bin/phpunit tests

abstract class DbTestCase extends TestCase
{

    protected Container $container;
    protected Explorer $database;

    protected function setUp(): void
    {
        $this->container = new TestBootstrap()->bootTestApplication();
        $this->database = $this->container->getByType(Explorer::class);
    }

    protected function tearDown(): void
    {
        // Restore handlers to fix warnings
        restore_error_handler();
        restore_exception_handler();
    }
 
    protected function logInfo($message)
    {
        Debugger::log($message);
    }

}