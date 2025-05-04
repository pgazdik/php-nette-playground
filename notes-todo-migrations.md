## General


### Migrations

For managing the DB schemas use dibi migrations.


The most popular and recommended migration tool for Nette applications is dibi/migrations (or its spiritual successor, contributte/migrations).



### Install the Migration Tool:

```bash
composer require dibi/migrations
```

OR if you prefer Contributte's version which is more actively maintained:
```bash
composer require contributte/migrations
```

(I'll use dibi/migrations in the example as it's conceptually simpler for a quick start, but contributte/migrations is more modern and feature-rich).

### Configure Nette for Migrations:
You'll need to register the migrations extension in your `config.neon`.

```
# config.neon
extensions:
    migrations: Dibi\Migrations\Bridges\Nette\Extension

migrations:
    # Directory where your migration files will be stored
    dirs: %appDir%/migrations
    # Optional: Name of the table that stores executed migrations (defaults to '__migrations')
    table: __migrations
    # Optional: Database connection service name (defaults to 'database.default' or 'database')
    # service: database.default
```

### Create a Migration File:
Migration files are typically PHP classes that define `up()` and `down()` methods.
You'd usually use a command-line tool provided by the migration library to generate a new migration file.

For `dibi/migrations`, you'd add a console command entry in your `config.neon` and then run a command:

```neon
# config.neon (add to your console configuration)
console:
    commands:
        - Dibi\Migrations\Bridges\Symfony\Console\MigrateCommand
        - Dibi\Migrations\Bridges\Symfony\Console\GenerateCommand
```
Then, from your project root:

```bash
php www/index.php migrations:generate create_sms_table
```

This would create a file like `app/migrations/YYYYMMDD_HHMMSS_create_sms_table.php`:

```php
<?php

declare(strict_types=1);

namespace App\Migrations;

use Dibi\Connection; // Or your specific DB connection class

final class YYYYMMDD_HHMMSS_create_sms_table
{
    private Connection $connection; // Or your DB service name from config

    public function __construct(Connection $connection) // Injected database connection
    {
        $this->connection = $connection;
    }

    public function up(): void
    {
        $this->connection->query('
            CREATE TABLE sms (
                id INT PRIMARY KEY AUTO_INCREMENT,
                recipient_number VARCHAR(20) NOT NULL,
                message_text TEXT NOT NULL,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                status VARCHAR(50) DEFAULT 'pending'
            );
        ');
    }

    public function down(): void
    {
        $this->connection->query('DROP TABLE IF EXISTS sms;');
    }
}
```

(Note: The Connection class might be Nette\Database\Explorer if you're using Nette Database, or Dibi\Connection if you're using Dibi directly. Adjust the type hint accordingly based on what your database service provides.)

### Run the Migrations:

```bash
php www/index.php migrations:migrate
```
This command checks the `__migrations` table. If it doesn't exist, it creates it. Then, it runs any `up()` methods for migration files that haven't been executed yet.