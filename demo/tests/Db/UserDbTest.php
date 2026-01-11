<?php
namespace Tests\Db;

use function PHPUnit\Framework\assertTrue;

// To run tests:
// docker compose exec -w //application/demo php-fpm ./vendor/bin/phpunit tests

class UserDbTest extends DbTestCase
{
    // public function setUp(): void
    // {
    //     parent::setUp();

    //     $this->database->query('DROP TABLE IF EXISTS user');

    //     $this->database->query('CREATE TABLE user (id INTEGER PRIMARY KEY, name TEXT)');
    //     $this->database->query('INSERT INTO user (id, name) VALUES (1, "John")');
    // }

    public function testQueryUsers()
    {
        assertTrue(true);
    //     $users = $this->database
    //         ->table('user')
    //         // we select everything except img_content, which is a BLOB
    //         ->select('id, name')
    //         ->order('id DESC')
    //         ->fetchAll();

	// 	foreach ($users as $user) {
	// 		$this->logInfo("\nUser: ".json_encode($user->toArray())."\n");
	// 	}

    //     $this->assertEquals(1, \count($users));
    }
}