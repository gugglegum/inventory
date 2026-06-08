# PHPUnit tests

Тесты используют отдельную базу данных `stockhub_test` в контейнере MariaDB.

Подготовка тестовой базы из корня репозитория:

```bash
docker compose exec db mariadb -uroot -proot -e "DROP DATABASE IF EXISTS stockhub_test; CREATE DATABASE stockhub_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON stockhub_test.* TO 'stockhub'@'%'; FLUSH PRIVILEGES;"
docker compose exec php php tests/phpunit/bin/yii-test migrate --interactive=0
```

Запуск тестов из корня репозитория:

```bash
docker compose exec php ./vendor/bin/phpunit -c phpunit.xml
```

Или из каталога `app/`:

```bash
docker compose exec php ./vendor/bin/phpunit -c phpunit.xml
```

Тестовая конфигурация не подключает обычные `main-local.php`: подключение к БД задаётся через `tests/phpunit/config/common.php` и переменные окружения из `phpunit.xml`.

Runtime-файлы тестов, опубликованные assets, фотографии и миниатюры создаются через alias `@phpunitRuntime` в системном `/tmp`, чтобы запуск внутри Docker не зависел от прав на рабочий checkout.
