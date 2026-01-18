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

		// Needed becaue appDir is the parent containing the bootstrap file - the file that creates Configurator instance
		// 				'appDir' => isset($trace[1]['file']) ? dirname($trace[1]['file']) : null,
		// As our we are not within app/, but tests/, we need to override it
		$this->configurator->addParameters([
			'appDir' => $this->rootDir . '/app',
		]);

	}

	public function bootTestApplication(): Nette\DI\Container
	{
		$this->initializeEnvironment();
		$this->setupContainer();
		return $this->configurator->createContainer();
	}

	private function initializeEnvironment(): void
	{
		// We just configure log directory so logging works, but we don't call enableTracy as that would lead to problems with custom exception and error handlers
		// * Test code or tested code did not remove its own error handlers
		// * Test code or tested code did not remove its own exception handlers

		//OR

		// Test code or tested code removed error handlers other than its own

		$logDir = "$this->rootDir/log/test";
		if (!file_exists($logDir)) {
			mkdir($logDir, 0777, true);
		}
		\Tracy\Debugger::$logDirectory = $logDir;

		//$this->configurator->enableTracy($logDir);

		// To see stacktrace
		// $this->configurator->setDebugMode(true);
		// This was here by default, no idea why.
		//$this->configurator->setDebugMode('secret@23.75.345.200'); // enable for your remote IP


		$this->configurator->createRobotLoader()
			->addDirectory(__DIR__)
			->register();
	}

	private function setupContainer(): void
	{
		$configDir = $this->rootDir . '/config';
		$this->configurator->addConfig("$configDir/common.neon");
		$this->configurator->addConfig("$configDir/db-test.neon");
		$this->configurator->addConfig("$configDir/migrations.neon");
		$this->configurator->addConfig("$configDir/services.neon");
		$this->configurator->addConfig("$configDir/services-test.neon");
	}
}
