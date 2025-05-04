<?php
namespace Tests\Tested;

use App\Tested\Calculator;
use PHPUnit\Framework\TestCase;


// To run tests:
// docker compose exec -w //application/demo php-fpm ./vendor/bin/phpunit tests

class CalculatorTest extends TestCase {
    public function testAdd() {
        $calculator = new Calculator();
        $result = $calculator->add(2, 3);
        $this->assertEquals(5, $result);
    }
}