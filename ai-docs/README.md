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

Проект основан на Yii 2 Advanced Application Template. `composer.json` находится в `app/`. Минимальное требование в Composer - PHP `>=8.0.0`, а текущий Docker-образ использует `php:8.4-fpm`.

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

`composer validate` проходил без ошибок, но показывал предупреждения о незафиксированных версиях (`*`) у `yiisoft/yii2-swiftmailer` и `kartik-v/yii2-widget-datetimepicker`. `requirements.php` не находил критических ошибок, но предупреждал об отсутствующих/необязательных компонентах вроде `intl/ICU`, `pcntl`, `memcache/APC`, `ImageMagick` и включенном `expose_php`.
