# Setup unit testing

## Basic Unit Test

### Add phpunit as a dependency

> docker compose exec -w //application/demo php-fpm composer require --dev phpunit/phpunit

### Add `autoload-dev` to `composer.json`

```json
"autoload": {
    "psr-4": {
        "App\\": "app"
    }
},
"autoload-dev": {
    "psr-4": {
        "Tests\\": "tests"
    }
}
```

### Update autoloader
> docker compose exec -w //application/demo php-fpm composer dump-autoload --dev

### Create test file

```php
<?php
namespace Tests\Tested;

class CalculatorTest extends TestCase {
    public function testAdd() {
        $calculator = new Calculator();
        $result = $calculator->add(2, 3);
        $this->assertEquals(5, $result);
    }
}
```

### Run tests

#### All
> docker compose exec -w //application/demo php-fpm ./vendor/bin/phpunit tests --testdox

`./vendor/bin/phpunit` is the path to the phpunit executable.
`tests` is the directory where the test files are located.
`--testdox` is optional, it will show a more readable output.

#### Single Class
> docker compose exec -w //application/demo php-fpm ./vendor/bin/phpunit tests --filter CalculatorTest --testdox

`--filter CalculatorTest` specifies the class name

