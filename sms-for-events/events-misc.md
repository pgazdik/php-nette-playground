## Purge DB

Delete all tables

```bash
docker compose exec db-server mariadb --user=cortex --password=cortex db -e "drop table if exists migrations, notification_attempt, notification_msg, event;"
```

Run migrations script
```bash
docker compose exec -w //application/demo php-fpm php ./bin/console migrations:continue
```