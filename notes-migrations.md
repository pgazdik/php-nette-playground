## General

### Install the Migration Tool:

```bash
composer require nextras/migrations
```

### Configure Nette for Migrations:
You'll need to register the migrations extension in a `config.neon`.

```
# config.neon
extensions:
    migrations: Nextras\Migrations\Bridges\NetteDI\MigrationsExtension

migrations:
    # This is the directory where your migration files (SQL or PHP) will live
    dir: %appDir%/migrations
    # The driver for your database
    driver: mysql # or pgsql
    # Crucially, tell it to use the Nette Database Adapter
    dbal: nette
```

### Migrations files

Nextras expects `demo/app/migrations` to be organized into subdirectories based on the type of change: 
```
migrations/
├── structure/
├── data/
└── dummy/
```

For DDL like creating a table, we add an SQL file to the `structure` directory, called e.g. `2025-12-31-18-00-00_create_my_table.sql`.


### Run the Migration

```bash
docker compose exec -w //application/demo php-fpm php ./bin/console migrations:continue
```

This creates a `migrations` table if not exists and applies all not yet applied migrations.
