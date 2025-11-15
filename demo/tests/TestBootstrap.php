<?php

declare(strict_types=1);

namespace Tests;

use Nette;
use Nette\Bootstrap\Configurator;

class TestBootstrap
{
	private Configurator $configurator;
	private string $rootDir;


	public function __construct()
	{
		$this->rootDir = dirname(__DIR__); // i.e. parent of __DIR__ - i.e. /demo
		$this->configurator = new Configurator;
		$this->configurator->setTempDirectory($this->rootDir . '/temp');

	}

	public function bootTestApplication(): Nette\DI\Container
	{
		$this->initializeEnvironment();
		$this->setupContainer();
		return $this->configurator->createContainer();
	}

	private function initializeEnvironment(): void
	{
		$logDir = $this->rootDir . '/log/test';
		if (!file_exists($logDir)) {
			mkdir($logDir, 0777, true);
		}

		// To see stacktrace
		$this->configurator->setDebugMode(true);
		// This was here by default, no idea why.
		//$this->configurator->setDebugMode('secret@23.75.345.200'); // enable for your remote IP
		$this->configurator->enableTracy($logDir);

		$this->configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();
	}


	private function setupContainer(): void
	{
		$configDir = $this->rootDir . '/config';
		$this->configurator->addConfig($configDir . '/common.neon');
		$this->configurator->addConfig($configDir . '/db-test.neon');
		$this->configurator->addConfig($configDir . '/services.neon');
	}
}
