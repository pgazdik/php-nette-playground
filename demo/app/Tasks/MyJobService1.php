<?php
namespace Task;

use Tracy\Debugger;

class MyJobService1
{	
    public function __construct(
        private \Nette\Database\Explorer $database
    ) {
    }

	public function run()
	{
		Debugger::log("Running job 1");
		// $this->setUp();
		// $this->testQueryUsers();
	}

	private function setUp(): void
	{
		$this->database->query('DROP TABLE IF EXISTS userr');
		$this->database->query('CREATE TABLE userr (id INTEGER PRIMARY KEY, name varchar(255))');
		$this->database->query('INSERT INTO userr (id, name) VALUES (1, "John")');
		$this->database->query('INSERT INTO userr (id, name) VALUES (3, "Jack")');
	}

	private function testQueryUsers()
	{
		$users = $this->database
			->table('userr')
			->select('id, name')
			->fetchAll();

		foreach ($users as $user) {
			Debugger::log("\nUser: ".json_encode($user->toArray())."\n");
		}
	}
}


