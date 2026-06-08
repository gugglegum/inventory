# Памятка по проекту Stockhub

Stockhub - это PHP/Yii 2 приложение для домашнего складского учета. Исторически в Git находился только каталог приложения, но после небольшой трансформации корень репозитория поднят на уровень выше: теперь в Git находится весь проект с Docker-обвязкой, а Yii-приложение лежит в `app/`.

## Структура репозитория

- `app/` - основное Yii 2 Advanced приложение.
- `app/backend/` - основной веб-интерфейс складского учета; nginx смотрит в `app/backend/web`.
- `app/frontend/` - часть стандартной структуры Yii Advanced, сейчас не является основной точкой входа.
- `app/common/` - общие модели, компоненты и вспомогательный код.
- `app/console/` - консольные команды и миграции.
- `app/console/migrations/` - история миграций базы данных.
- `app/photos/` - пользовательские фотографии; реальные файлы не должны попадать в Git.
- `app/thumbnails/` - миниатюры фотографий; генерируемые файлы не должны попадать в Git.
- `docker/` - Dockerfile и конфиги для PHP-FPM и nginx.
- `docker-compose.yml.example` - отслеживаемый пример compose-файла.
- `docker-compose.yml` - локальный compose-файл, находится в корне проекта и игнорируется Git.
- `db_data/` - локальные данные MariaDB, игнорируются Git.
- `logs/` - локальные логи контейнеров/nginx, игнорируются Git.
- `ai-docs/` - проектная память для будущих AI-агентов.

## Локальная Docker-схема

Проект запускается через Docker Compose из корня репозитория. Локальный `docker-compose.yml` не коммитится: его можно менять под текущую машину, не смешивая это с переносимыми файлами проекта.

Основные сервисы:

- `php` / контейнер `stockhub-php` - PHP-FPM, образ `stockhub-php:latest`, сборка из `docker/php/Dockerfile`.
- `nginx` / контейнер `stockhub-nginx` - внутренний nginx приложения, образ `stockhub-nginx:latest`, сборка из `docker/nginx/Dockerfile`.
- `db` / контейнер `stockhub-db` - MariaDB.

Приложение монтируется в PHP и nginx как `/var/www/html`, а рабочая директория PHP-контейнера - `/var/www/html`. Поэтому команды Yii и Composer обычно выполняются внутри контейнера из корня `app/`.

Локальный запуск использует общий reverse proxy из `/home/paul/PhpstormProjects/proxy`. Контейнер `stockhub-nginx` подключается одновременно к внутренней сети приложения и к внешней Docker-сети `proxy`. Сейчас локальный compose публикует приложение через `VIRTUAL_HOST=stockhub.lc`.

Важно: когда nginx подключен и к сети приложения, и к общей сети `proxy`, нельзя использовать `fastcgi_pass php:9000`. На общей сети другой проект может иметь DNS-алиас `php`, и запросы уйдут не в тот контейнер. В nginx-конфиге нужно использовать уникальное имя контейнера:

```nginx
fastcgi_pass stockhub-php:9000;
```

Внутренний nginx слушает `stockhub.lc` и `stockhub.ru` через `server_name`. TLS/сертификаты в этом локальном проекте не нужны; если HTTPS используется, он должен завершаться на общем reverse proxy. Каталог `certbot/` не нужен в репозитории и не должен попадать в Git.

## Приложение

Проект основан на Yii 2 Advanced Application Template. `composer.json` находится в `app/`. Минимальное требование в Composer - PHP `>=8.4`, а текущий Docker-образ использует `php:8.4-fpm`.

Ключевые зависимости и расширения:

- `yiisoft/yii2`
- `yiisoft/yii2-bootstrap`
- `yiisoft/yii2-swiftmailer`
- `s9e/text-formatter`
- `kartik-v/yii2-widget-datetimepicker`
- PHP-расширения `gd`, `exif`, `mbstring`, `pdo_mysql`

Фотографии хранятся в `app/photos/`, миниатюры - в `app/thumbnails/`. Эти каталоги могут быть большими и содержат пользовательские данные, поэтому в Git должны попадать только `.gitignore`-заглушки.

## База данных и миграции

Локальная база работает в контейнере MariaDB. В compose используются стандартные локальные параметры:

- база: `stockhub`
- пользователь: `stockhub`
- пароль: `stockhub`
- root-пароль: `root`

Данные MariaDB лежат в `db_data/` и не коммитятся. Миграции находятся в `app/console/migrations/`.

Полезные команды из корня репозитория:

```bash
docker compose ps
docker compose up -d --build
docker compose exec php composer install
docker compose exec php ./yii migrate
docker compose exec php ./yii migrate/new
docker compose exec php ./yii migrate/history 10
```

В корне и в `app/` есть небольшие shell-скрипты для типовых операций: входа в MariaDB, дампа/импорта базы, установки Composer-зависимостей, запуска миграций и пересоздания миниатюр. Перед изменением таких скриптов учитывай, из какой директории они предполагают запуск.

## Тесты

Для новой разработки добавлена отдельная PHPUnit-инфраструктура в `app/tests/phpunit/`. Она не использует старую Codeception-обвязку из шаблона Yii Advanced. Тесты ориентированы на PHPUnit 13 и PHP 8.4.

Тесты запускаются внутри PHP-контейнера и используют отдельную базу `stockhub_test`. Тестовый Yii-конфиг находится в `app/tests/phpunit/config/` и не подключает обычные `main-local.php`, чтобы случайно не работать с локальной рабочей базой `stockhub`.

Подготовка тестовой базы из корня репозитория:

```bash
docker compose exec db mariadb -uroot -proot -e "DROP DATABASE IF EXISTS stockhub_test; CREATE DATABASE stockhub_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON stockhub_test.* TO 'stockhub'@'%'; FLUSH PRIVILEGES;"
docker compose exec php php tests/phpunit/bin/yii-test migrate --interactive=0
```

Запуск PHPUnit из корня репозитория:

```bash
docker compose exec php ./vendor/bin/phpunit -c phpunit.xml
```

На момент первичной настройки все миграции успешно проходили с нуля на чистой `stockhub_test`. Первый набор тестов покрывает bootstrap схемы, базовую модель пользователя, создание репозитория и предметов, сохранение тегов и поведение soft delete в `Item::find()`.

Следующая пачка расширила regression-покрытие на права доступа к репозиториям, поиск предметов через `ItemsController::actionSearch()`, подтверждённый импорт предметов через `ItemsController::actionImport()`, валидацию форм подтверждения инвентаризации и закрытие инвентаризации через `InventoryController::actionClose()`.

Первый рефакторинг после появления тестов вынес поиск и импорт из `ItemsController` в сервисы `backend/services/ItemSearchService.php` и `backend/services/ItemImportService.php`. Контроллер после этого отвечает в основном за HTTP-параметры, редиректы и render, а сервисы - за построение поискового результата, разбор текста импорта и создание импортируемых предметов.

Следующий шаг вынес закрытие инвентаризации из `InventoryController::actionClose()` в `backend/services/InventoryCloseService.php`. Сервис обновляет подтвержденные предметы, возвращает их в контейнер инвентаризации, отмечает неподтвержденные дочерние предметы как отсутствующие и закрывает саму инвентаризацию. Операция выполняется в транзакции, чтобы не оставить инвентаризацию частично закрытой при ошибке сохранения. Для сервиса добавлен прямой integration-тест `tests/phpunit/integration/InventoryCloseServiceTest.php`, а контроллерный тест закрытия инвентаризации остался regression-защитой HTTP-слоя.

После этого из `InventoryController::actionView()` вынесены POST-мутации подтверждения и снятия подтверждения предметов. Новый сервис `backend/services/InventoryItemConfirmationService.php` создает и удаляет записи `inventory_item`, а контроллер оставляет за собой загрузку форм, поиск моделей, редиректы и render. Поведение защищено controller-тестами confirm/unconfirm в `tests/phpunit/integration/InventoryTest.php` и прямым integration-тестом сервиса `tests/phpunit/integration/InventoryItemConfirmationServiceTest.php`.

Read-часть `InventoryController::actionView()` вынесена в `backend/services/InventoryViewDataService.php`. Сервис готовит `confirmedItems`, `notConfirmedItems` и `paths` для шаблона `inventory/view`, а DTO `backend/services/InventoryViewData.php` переносит эти данные обратно в контроллер. Контракт сервиса покрыт `tests/phpunit/integration/InventoryViewDataServiceTest.php`, включая случай, когда в инвентаризации еще нет подтвержденных предметов.

## Git и локальные файлы

В корневом `.gitignore` намеренно игнорируются:

- `/docker-compose.yml`
- `/db_data/`
- `/logs/`
- локальные nginx-файлы в `docker/nginx/`
- локальный PHP-конфиг `docker/php/custom.ini`
- служебные каталоги `.agents/` и `.codex`

`docker-compose.yml.example`, `docker/php/custom.ini.example` и `docker/nginx/default.conf.example` являются переносимыми примерами и должны оставаться в Git.

Если после перестройки структуры в индексе снова появится старый `app/docker-compose.yml`, это, скорее всего, след от прежней структуры проекта. Его не нужно коммитить; compose-файл проекта теперь находится в корне.

## Проверенное состояние

После переноса корня репозитория приложение успешно запускалось из корня проекта через Docker Compose. Контейнеры `stockhub-db`, `stockhub-nginx` и `stockhub-php` поднимались, `stockhub.lc` отвечал редиректом на страницу входа, Yii видел историю миграций, а новых миграций к применению не было.

`composer validate` проходит без ошибок. Ранее предупреждения о незафиксированных версиях (`*`) у `yiisoft/yii2-swiftmailer` и `kartik-v/yii2-widget-datetimepicker` были закрыты явными ограничениями. Yii обновлен до ветки `2.0.55`, PHPUnit - до `13.2`. `requirements.php` не находил критических ошибок, но предупреждал об отсутствующих/необязательных компонентах вроде `intl/ICU`, `pcntl`, `memcache/APC` и `ImageMagick`; `expose_php` был отключен отдельно.
