## General


### Quick commands

Run bask in php-fpm container

> docker compose exec php-fpm bash

Install dependencies
> docker compose exec -w /application/demo php-fpm composer install

Build php-npm image
> docker compose build php-npm

Query DB
> docker compose exec db-server mariadb --user=cortex --password=cortex db -e "SELECT * FROM posts;"


---------



## Setup Explanation

### Folder structure

I use a subfolder `demo`, because creating the project with composer needed an empty folder.

The command was:
> docker compose exec php-fpm composer create-project nette/web-project demo

Then the public directory as configured in nginx was `demo/www`, configured as:
> root /application/demo/www.

### File Permissions

In order to set the permissions when the container starts I've added the following to the docker-compose.yml file:

```yml
command: >
    /bin/sh -c "
    chown -R www-data:www-data /application/demo/www /application/demo/log /application/demo/temp &&
    chmod -R 777 /application/demo/log /application/demo/temp &&
    chmod -R 755 /application/demo/www &&
    php-fpm8.4"
```

The `php-fpm8.4` command is used to start the PHP-FPM server. This command is located in `/usr/sbin/php-fpm8.4`.

BTW, Grok though it might need `php-fpm8.4 --nodaemonize`