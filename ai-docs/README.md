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

Доменная модель фотографий устроена так: сам файл описывает `common/models/Photo`, а привязка к месту использования хранится в `ItemPhoto` или `PostPhoto`. На уровне текущего поведения связь фактически 1 к 1: одна фотография используется только в одном месте. Исторически файл фотографии жил прямо в `ItemPhoto`; после появления фотографий у заметок общая часть была вынесена в `Photo`, чтобы не дублировать файловую логику.

Миниатюры сейчас работают как кэш: при удалении основной фотографии оригинальный файл удаляется, а уже созданные thumbnails могут оставаться в `app/thumbnails/`. Это ожидаемое текущее поведение. В будущем можно добавить чистку thumbnails при удалении `Photo`, но это отдельное изменение с риском задеть генерацию/кэширование миниатюр.

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

Первый рефакторинг после появления тестов вынес поиск и импорт из `ItemsController` в сервисы `backend/services/ItemSearchService.php` и `backend/services/ItemImportService.php`. Контроллер после этого отвечает в основном за HTTP-параметры, редиректы и render, а сервисы - за построение поискового результата, разбор текста импорта и создание импортируемых предметов. Подтвержденный импорт в `ItemImportService` выполняется одной транзакцией: при ошибке сохранения любого предмета или его тегов откатываются уже созданные предметы, теги и обновление `repo.lastItemId`. Это покрыто прямым integration-тестом `tests/phpunit/integration/ItemImportServiceTest.php`.

Удаление предметов из `ItemsController::actionDelete()` вынесено в `backend/services/ItemDeletionService.php`. `backend/models/ItemDeleteForm.php` теперь остается формой выбора режима удаления и контейнером ошибок, а сервис выполняет мягкое или жесткое удаление предмета. Поведение защищено controller-тестами soft/hard delete в `tests/phpunit/integration/ItemsControllerTest.php` и прямым integration-тестом сервиса `tests/phpunit/integration/ItemDeletionServiceTest.php`.

Сохранение связанных данных формы предмета вынесено из `ItemsController::actionCreate()` и `ItemsController::actionUpdate()` в `backend/services/ItemFormAssetService.php`. Сервис сохраняет теги и прикрепляет новые фотографии к предмету после успешного сохранения `Item`; контроллеры create/update теперь оставляют за собой подготовку модели, `load()`, `save()` и redirect/render. Контроллерные сценарии create/update с тегами покрыты в `tests/phpunit/integration/ItemsControllerTest.php`, а прямой сервисный тест `tests/phpunit/integration/ItemFormAssetServiceTest.php` проверяет сохранение тегов и реальное прикрепление JPEG-файла. Тестовый фото-конфиг в `tests/phpunit/config/common.php` использует реальные файловые пути через alias `@phpunitRuntime`, потому что `Photo::assignFile()` ожидает пути файловой системы, а не Yii alias.

Подготовка и сохранение самой модели `Item` для форм создания/редактирования вынесены в `backend/services/ItemFormService.php`. Сервис выставляет create/update scenario, access validator, repo/parent, createdBy/updatedBy, начальный флаг контейнера, готовит `ItemTagsForm` и выполняет `load()+save()` модели. Контракт покрыт `tests/phpunit/integration/ItemFormServiceTest.php`, а HTTP-сценарии create/update остаются защищены `tests/phpunit/integration/ItemsControllerTest.php`.

Read-часть `ItemsController::actionView()` и `ItemsController::actionJsonPreview()` вынесена в `backend/services/ItemViewDataService.php`. Сервис готовит дочерние предметы в порядке отображения, соседние предметы для prev/next навигации и path-данные для preview partial, а DTO `backend/services/ItemViewData.php` и `backend/services/ItemPreviewData.php` переносят эти данные обратно в контроллер. Контракт сервиса покрыт `tests/phpunit/integration/ItemViewDataServiceTest.php`, включая сортировку детей, соседние предметы и path URLs; HTTP-слой дополнительно проверяется render/json-preview сценариями в `tests/phpunit/integration/ItemsControllerTest.php`.

Create/update формы заметок вынесены из `PostsController` в `backend/services/PostFormService.php`. Сервис готовит create/update сценарии `Post`, служебные поля `createdBy`/`updatedBy`, сохраняет форму и прикрепляет новые фотографии через `Photo` + `PostPhoto`; контроллер оставляет за собой поиск repo/item/post, render и redirect. Удаление заметок вынесено в `backend/services/PostDeletionService.php`. Контроллерные сценарии create/update/delete покрыты в `tests/phpunit/integration/PostsControllerTest.php`, а прямые контракты сервисов - в `tests/phpunit/integration/PostFormServiceTest.php` и `tests/phpunit/integration/PostDeletionServiceTest.php`.

Управление существующими фотографиями предметов и заметок вынесено из `PhotoController` в `backend/services/PhotoAttachmentService.php`. Сервис поддерживает два типа связей, `ItemPhoto` и `PostPhoto`, сортирует фотографии внутри правильного списка и удаляет нужную связь. Форма предмета и форма заметки передают в AJAX явный `photoType` (`item` или `post`), а `backend/web/js/upload_photo.js` отправляет этот тип вместе с ID связи. Это исправляет прежний дефект, когда сортировка и удаление фотографий работали у предметов, но не работали у заметок. Поведение покрыто `tests/phpunit/integration/PhotoAttachmentServiceTest.php`, `tests/phpunit/integration/PhotoControllerTest.php` и render-проверкой формы заметки в `tests/phpunit/integration/PostsControllerTest.php`.

Подготовка create/update форм репозитория вынесена из `RepoController` в `backend/services/RepoFormService.php`. Сервис готовит `RepoForm` для создания и редактирования, заполняет update-форму текущими значениями `Repo` + `RepoUser` и делегирует сохранение самой форме. Удаление репозитория и расчет `affectedUsers` вынесены в `backend/services/RepoDeletionService.php`. Контроллерные сценарии create/update/delete покрыты в `tests/phpunit/integration/RepoControllerTest.php`, а прямые контракты сервисов - в `tests/phpunit/integration/RepoFormServiceTest.php` и `tests/phpunit/integration/RepoDeletionServiceTest.php`.

Следующий шаг вынес закрытие инвентаризации из `InventoryController::actionClose()` в `backend/services/InventoryCloseService.php`. Сервис обновляет подтвержденные предметы, возвращает их в контейнер инвентаризации, отмечает неподтвержденные дочерние предметы как отсутствующие и закрывает саму инвентаризацию. Операция выполняется в транзакции, чтобы не оставить инвентаризацию частично закрытой при ошибке сохранения. Для сервиса добавлен прямой integration-тест `tests/phpunit/integration/InventoryCloseServiceTest.php`, а контроллерный тест закрытия инвентаризации остался regression-защитой HTTP-слоя.

После этого из `InventoryController::actionView()` вынесены POST-мутации подтверждения и снятия подтверждения предметов. Новый сервис `backend/services/InventoryItemConfirmationService.php` создает и удаляет записи `inventory_item`, а контроллер оставляет за собой загрузку форм, поиск моделей, редиректы и render. Поведение защищено controller-тестами confirm/unconfirm в `tests/phpunit/integration/InventoryTest.php` и прямым integration-тестом сервиса `tests/phpunit/integration/InventoryItemConfirmationServiceTest.php`.

Read-часть `InventoryController::actionView()` вынесена в `backend/services/InventoryViewDataService.php`. Сервис готовит `confirmedItems`, `notConfirmedItems` и `paths` для шаблона `inventory/view`, а DTO `backend/services/InventoryViewData.php` переносит эти данные обратно в контроллер. Контракт сервиса покрыт `tests/phpunit/integration/InventoryViewDataServiceTest.php`, включая случай, когда в инвентаризации еще нет подтвержденных предметов.

Открытие и удаление инвентаризаций вынесены из `InventoryController::actionCreate()` и `InventoryController::actionDelete()` в `backend/services/InventoryLifecycleService.php`. Контроллерные сценарии создания/удаления покрыты в `tests/phpunit/integration/InventoryTest.php`, а прямой контракт сервиса - в `tests/phpunit/integration/InventoryLifecycleServiceTest.php`.

Финальная уборка после основных шагов рефакторинга убрала старый закомментированный access-validator код вокруг `Post`/`PostPhoto` и устаревший закомментированный `PostsController::actionIndex()`. Общие test helpers для создания JPEG-фикстуры, `Photo`, `ItemPhoto` и `PostPhoto` перенесены в `tests/phpunit/DbTestCase.php`; там же тестовые request helpers теперь умеют задавать `REQUEST_URI` для render-сценариев форм. Дополнительное regression-покрытие закрывает `PostsController::actionView()` и GET-ветку `RepoController::actionUpdate()`.

Общий access/context lookup repo-aware контроллеров вынесен в `backend/controllers/RepoAwareController.php`. `ItemsController`, `PostsController`, `InventoryController` и `RepoController` наследуются от него и используют общие protected helpers `findRepo()`, `findItem()`, `findParentItem()`, `findRepoUser()`, `getItemAccessValidator()` и `getLoggedUser()`. Это закрыло прежнюю пометку о дублировании в `RepoController` и убрало повторяющиеся private helper-методы из нескольких контроллеров. Прямой контракт базового контроллера покрыт `tests/phpunit/integration/RepoAwareControllerTest.php`, а существующие controller-тесты остаются regression-защитой HTTP-слоя.

Read-side ветки `ItemsController::actionIndex()`, `ItemsController::actionPickContainer()` и `ItemsController::actionSearchContainer()` вынесены в `backend/services/ItemListService.php`. Сервис возвращает корневые предметы репозитория, готовит данные модального выбора контейнера через DTO `backend/services/ItemContainerPickerData.php` и делегирует поиск контейнеров существующему `ItemSearchService`. Контракт покрыт `tests/phpunit/integration/ItemListServiceTest.php`, а HTTP-render регрессии index/pick-container/search-container добавлены в `tests/phpunit/integration/ItemsControllerTest.php`.

Подготовка и сохранение create/update формы пользователя вынесены из `UsersController` в `backend/services/UserFormService.php`. `backend/models/UserForm.php` теперь использует константу `SCENARIO_CREATE` вместо строкового сценария `create`, а контроллер оставляет за собой redirect/render. Сервис покрыт `tests/phpunit/integration/UserFormServiceTest.php`, HTTP CRUD-сценарии пользователей - `tests/phpunit/integration/UsersControllerTest.php`, login/logout - `tests/phpunit/integration/SiteControllerTest.php`. Глобальные bitmask-права `UserAccess::canManageUsers()` и `UserAccess::canCreateRepo()` дополнительно закреплены в `tests/phpunit/integration/AccessTest.php`.

Каскадная логика удаления из ActiveRecord-моделей вынесена в common-сервисы: `common/services/ItemDeletionCascadeService.php` обслуживает `Item::softDelete()`, `Item::beforeSoftDelete()` и `Item::beforeDelete()`, а `common/services/RepoDeletionCascadeService.php` обслуживает `Repo::beforeDelete()`. Модельные методы и hooks оставлены как публичные точки входа, поэтому существующие вызовы `$item->delete()`, `$item->softDelete()` и `$repo->delete()` сохраняют поведение, но обход дочерних предметов, фотографий, заметок и root items больше не живет прямо в моделях. Поведение закреплено тестами `tests/phpunit/integration/ItemDeletionCascadeServiceTest.php` и `tests/phpunit/integration/RepoDeletionCascadeServiceTest.php`; существующие deletion/controller тесты дополнительно страхуют совместимость.

На 2026-06-08 вручную проверены вход, выход, создание предмета, создание заметки, оба сценария с несколькими картинками, а также жесткое удаление предмета. В этом ручном сценарии физически удалялись оригинальные фотографии и предмета, и связанной заметки; thumbnails оставались, что соответствует текущей кэш-семантике. Сложный сценарий удаления предмета-контейнера с вложенными контейнерами, предметами, заметками и фотографиями пока не проходил ручную проверку целиком.

## Качество кода

Для ручной проверки перед крупными изменениями добавлен общий quality gate. Git hook намеренно не добавлялся: полный прогон занимает заметное время и не должен блокировать каждый коммит.

Запуск из корня репозитория:

```bash
app/bin/check-quality
```

Скрипт работает в двух режимах: с хоста вызывает `docker compose exec -T php bin/check-quality`, а внутри PHP-контейнера запускает проверки напрямую из `app/`. Вывод разбит на секции (`Composer validation`, `PHPUnit`, `PHPStan`, `Psalm`, `PHPCS style`, `PHPCS PHP compatibility`), чтобы было видно, где заканчивается одна проверка и начинается следующая.

Те же проверки можно запускать отдельными Composer-командами внутри PHP-контейнера:

```bash
docker compose exec php composer run test
docker compose exec php composer run phpstan
docker compose exec php composer run psalm
docker compose exec php composer run phpcs
docker compose exec php composer run quality
```

Подключенные инструменты:

- PHPUnit 13.2 - regression/integration/unit тесты из `tests/phpunit/`.
- PHPStan 2.2 - текущий уровень `level: 3`, конфиг `phpstan.neon`.
- Psalm 6.16 через `psalm/phar`, текущий `errorLevel="4"`, конфиг `psalm.xml`. PHAR выбран потому, что обычный пакет `vimeo/psalm` в актуальных версиях конфликтует с PHPUnit 13 по `sebastian/diff`, а старые версии Psalm не подходят для текущего PHP 8.4-стека.
- PHPCS 3.13 - `phpcs.xml` проверяет PSR-12 на активно рефакторимом backend-контуре (`backend/controllers`, `backend/services`, `common/services`, `tests/static-analysis`), а `phpcs-compat.xml` отдельно прогоняет PHPCompatibility по широкому дереву приложения.

Psalm настроен без baseline. В конфиге подавлен типичный шум Yii/PHPUnit: route action методы и тестовые классы как unused, требование `#[Override]`, шаблонные параметры Yii-классов и Yii view-контекст. View-файлы не анализируются Psalm как обычные PHP-классы, потому что в них `$this` и переданные переменные живут в контексте шаблона.

Для Psalm добавлен отдельный stub `tests/static-analysis/psalm-yii-stubs.php`. Он не участвует в runtime-загрузке приложения, а только связывает generic-шаблон Yii `BaseYii<TUserIdentity>` с проектной моделью `common\models\User`. Благодаря этому Psalm корректно видит `Yii::$app->getUser()` как `yii\web\User<common\models\User>`, а сервисные phpdoc-сигнатуры с пользователем тоже указывают `yii\web\User<common\models\User>`. Для анализа `Yii::$app` намеренно типизирован как `yii\web\Application<common\models\User>|null`: основной код и тесты работают с web-приложением, а console-код в проекте не требует console-only компонентов.

PHPStan использует bootstrap `tests/static-analysis/bootstrap.php`, который подключает Yii и выставляет project aliases. Кэши PHPStan/Psalm пишутся в `/tmp` внутри контейнера, чтобы не зависеть от прав на `tests/phpunit/_runtime`.

View/mail-шаблоны стоит аннотировать через `/** @var Type $variable */`, а не старым Yii-стилем `/* @var $variable Type */`: это помогает и IDE, и PHPStan. Для `$this` в шаблонах тоже работает явная строка `/** @var \yii\web\View $this */`; отдельный `ignoreErrors` для view-шаблонов не нужен.

При переходе PHPStan на `level: 3` query-классы `common/models/*Query.php` приведены к generics Yii 2.0.55 через `@extends ActiveQuery<Model>`. Старые Gii-заглушки `all()` и `one()`, которые просто вызывали parent-методы и мешали выводу типов, удалены. Это не меняет runtime-поведение, потому что методы наследуются от Yii `ActiveQuery`.

При переходе Psalm на `errorLevel="5"` query-классы дополнительно сделаны generic-обертками над `ActiveQuery<TModel>`, а `ActiveRecord::find()` в моделях документирован как `@return SomeQuery<static>`. Это согласует кастомные query-классы с Yii 2.0.55, где базовый `ActiveRecord::find()` возвращает `ActiveQuery<static>`. Также явно обработаны `false|null` от GD/буферных функций в `ImageResize` и от `preg_replace_callback()` в `MarkdownFormatter`.

При переходе Psalm на `errorLevel="4"` исправлены оставшиеся строгие места: ленивое поле пользователя в `ItemAccessValidator` стало nullable, неявные scalar-приведения в фото/thumbnail console-коде сделаны явными, Yii `ColumnSchemaBuilder` в миграциях явно приводится к строке перед `addColumn()`/`alterColumn()`, а намеренный `shell_exec()` в console-команде запроса пароля подавлен локальным `@psalm-suppress ForbiddenCode`.

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
