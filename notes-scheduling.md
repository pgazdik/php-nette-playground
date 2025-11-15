# Scheduling

## Pre Requisites

### Install cron

First, we need to install `cron` in the `php-fpm` container.
> apt-get install cron

### Install php packages

We also need to install the `orisai/nette-scheduler` and `orisai/nette-console` packages.

```bash
docker compose exec -w /application/demo php-fpm composer require orisai/nette-scheduler
docker compose exec -w /application/demo php-fpm composer require orisai/nette-console
```

## Configuration

### Prepare Tasks

Let's configure two dummy tasks.

Add the following to the `common.neon` file:
```yml
orisai.scheduler:
    jobs:
        -
            expression: "* * * * *"
            callback: [@my.task1, 'run']
        -
            expression: "* * * * *"
            callback: [@my.task2, 'run']

services:
    my.task1: Task\MyJobService1
    my.task2: Task\MyJobService2
```

Each task is a simple class with a `run` method.

```php
<?php
namespace Task;

use Tracy\Debugger;

class MyJobService1
{
	public static function run()
	{
		Debugger::log("Running job #1.");
	}
}
```

### Prepare the scripts

Under `bin` create a `scheduler.php` file, which will be called from the outside.

```php
#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

$bootstrap = new App\Bootstrap;
$container = $bootstrap->bootWebApplication();

use Orisai\Scheduler\Scheduler;
$scheduler = $container->getByType(Scheduler::class);
$scheduler->run();
```

This runs the `Scheduler`, which reads the jobs from `common.neon`.
For each job it runs a `console` script passing `scheduler:run-job <JOB_NUMBER>` as arguments.
`<JOB_NUMBER>` is the index of the job from `common.neon` under `orisai.scheduler.jobs`.

`console` script:
```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$bootstrap = new App\Bootstrap;
$container = $bootstrap->bootWebApplication();

exit($container
    ->getByType(\Symfony\Component\Console\Application::class)
    ->run()
);
```

See the passed arguments via:
```php
use Tracy\Debugger;
Debugger::log('ARGS: ' . implode(' ', $argv));
```

### Configure cron

Now we just need to configure cron to call our `scheduler.php` every minute (that's as often as it gets).

Add the following to the `command` of the `php-fpm` service in the `docker-compose.yml` file:
```yml
command: >
    echo '* * * * * cd /application/demo && php bin/scheduler.php >> log/scheduler.log 2>&1' | crontab - &&
    service cron start
```



