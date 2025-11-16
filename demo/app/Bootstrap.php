<?php

declare(strict_types=1);

namespace App;

use Nette;
use Nette\Bootstrap\Configurator;

class Bootstrap
{
	private Configurator $configurator;
	private string $rootDir;


	public function __construct()
	{
		$this->rootDir = dirname(__DIR__);
		$this->configurator = new Configurator;
		$this->configurator->setTempDirectory($this->rootDir . '/temp');
	}


	public function bootApplication(): Nette\DI\Container
	{
		$this->initializeEnvironment();
		$this->setupContainer();
		return $this->configurator->createContainer();
	}


	private function initializeEnvironment(): void
	{
		// To see stacktrace
		$this->configurator->setDebugMode(true);
		// This was here by default, no idea why.
		//$this->configurator->setDebugMode('secret@23.75.345.200'); // enable for your remote IP
		$this->configurator->enableTracy($this->rootDir . '/log');

		$this->configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();
	}

	private function setupContainer(): void
	{
		// Configuration (neon files) doc: https://doc.nette.org/en/dependency-injection/configuration
		// Special doc for services:       https://doc.nette.org/en/dependency-injection/services

		$configDir = $this->rootDir . '/config';
		$this->configurator->addConfig($configDir . '/common.neon');
		$this->configurator->addConfig($configDir . '/scheduler.neon');
		$this->configurator->addConfig($configDir . '/migrations.neon');
		$this->configurator->addConfig($configDir . '/db-prod.neon');
		$this->configurator->addConfig($configDir . '/services.neon');
	}
}
