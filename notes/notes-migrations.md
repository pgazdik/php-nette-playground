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
├── structures/
├── basic-data/
└── dummy-data/
```

For DDL like creating a table, we add an SQL file to the `structure` directory, called e.g. `2025-12-31-18-00-00_create_my_table.sql`.

> NOTE: Files are executed in lexicographical order.


Doc: https://nextras.org/migrations/docs/master/

### Run the Migration

```bash
docker compose exec -w //application/demo php-fpm php ./bin/console migrations:continue
```

This creates a `migrations` table if not exists and applies all not yet applied migrations.


Now, when an event is created, we also want to create a notification message (NotificationMsg) in our app. I suggest we refactor the code a little. Our Presenter won't call EventService directly, but an Event manager, with an instance of an Event. Event manager will then call the existing EventService and a new NotificationService to create the Event but also the NotificationMsg, respectively. The NotificationMsg will be of NotificationType "main" and MsgType "text", and the sendAt will be 7 days before the Event's appointment date. However, if the appointment is within 7 days, the sendAt will be set to current time. The text should be a informing the patient about his appointment with given doctor. If the event in question also has an attachementContent, then a second NotificationMsg with the same sendAt will be created, but this will be of MsgType "image", with no text.