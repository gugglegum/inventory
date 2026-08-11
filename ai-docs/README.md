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
- `deploy/nginx-proxy/stockhub.ru.conf.example` - tracked шаблон внешнего nginx для домашнего production.
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
Этот checkout обслуживает только локальное окружение и не описывает proxy,
через который Stockhub публикуется на домашнем production-сервере.

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

С 2026-08-11 публичные URL фотографий не должны указывать напрямую на
`app/photos` или `app/thumbnails`. `Photo::getUrl()` и
`Photo::getThumbnailUrl()` формируют маршруты `/photo/<id>.jpg` и
`/photo/<id>/thumbnail`; `PhotoController` требует авторизованного пользователя,
а `backend/services/PhotoAccessService` разрешает чтение только при наличии
`repo_user` для репозитория связанного предмета или заметки. После проверки
`backend/services/PhotoDeliveryService` возвращает пустой ответ с
`X-Accel-Redirect`, private cache headers и ETag. Внутренний nginx отдает тело
из `/_protected-photos/` или `/_protected-thumbnails/`; оба location имеют
директиву `internal`, поэтому прямой внешний запрос получает 404.

При deployment нужно синхронизировать tracked
`docker/nginx/default.conf.example` с фактическим игнорируемым
`docker/nginx/default.conf`, выполнить `nginx -t` и graceful reload. Обновление
PHP без одновременного обновления nginx приведет к 404 на новых внутренних URI,
а сохранение старых публичных `location /photos` и `/thumbnails` оставит обход
авторизации. Локально целевые controller-тесты, полный `app/bin/check-quality`
(183 теста / 1577 assertions), `nginx -t` и direct-URL smoke прошли; production
rollout этой защиты пока не выполнялся.

## Граница POST-форм и ActiveRecord

После рефакторинга 2026-06-09 сырые POST-данные не должны загружаться напрямую в ActiveRecord-модели через `load()`. `Model::load()` используется только у form/search моделей, а AR получает значения уже после валидации формы через прямое присваивание с явным приведением типов.

Для POST-форм публичные свойства объявляются строками (`public string $field = ''`), потому что из HTML-форм приходят строки. Числовые и булевые значения преобразуются в `int`/`bool` только в методах вроде `save()`, `getItemId()` или `isHardDelete()`. Если вместо строки в форму придет массив, текущий осознанно принятый риск - `TypeError` и HTTP 500; отдельная защита от array-инъекций пока не добавлялась.

Основные формы над AR:

- `backend/models/ItemForm.php` оборачивает `common/models/Item`, валидирует поля create/update предмета и сохраняет в AR уже `int|null` для `parentItemId`, `int` для `isContainer`/`priority` и строки для текстовых полей.
- `backend/models/PostForm.php` оборачивает `common/models/Post`, хранит `datetimeText` как строку формы, валидирует и парсит его в unix timestamp перед записью в `Post.datetime`.
- `backend/models/RepoForm.php` сама валидирует строки формы репозитория и вручную переносит значения в `Repo` и `RepoUser`; `Repo::load()` и `RepoUser::load()` там больше не используются.
- `backend/models/UserForm.php`, `ItemTagsForm`, `ItemDeleteForm`, `InventoryItem*Form`, `common/models/LoginForm.php` и frontend-формы также приведены к строковым публичным полям.

`common/models/Post.php` больше не содержит виртуальное поле `datetimeText` и не парсит пользовательскую дату в `beforeValidate()`: это ответственность `PostForm`. `common/models/UserSearch.php` больше не наследуется от `User`, а является обычной `Model`, чтобы даже GET-поиск не вызывал `load()` на AR-наследнике.

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

Тесты запускаются внутри PHP-контейнера и используют отдельную базу `stockhub_test`.
Тестовый Yii-конфиг находится в `app/tests/phpunit/config/`; его финальный
`params` хранится в переменной `$testParams`, чтобы подключаемый
`backend/config/main.php` не затёр тестовые параметры своей переменной `$params`.
В частности, фотографии и assets тестов реально направлены в отдельный
`@phpunitRuntime` под `/tmp`, а не в рабочие `app/photos` и `backend/web/assets`.

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

Сохранение связанных данных формы предмета вынесено из `ItemsController::actionCreate()` и `ItemsController::actionUpdate()` в `backend/services/ItemFormAssetService.php`. Сервис сохраняет теги через `ItemTagsForm` и прикрепляет новые фотографии к предмету после успешного сохранения `Item`; контроллеры create/update теперь оставляют за собой подготовку формы, redirect/render и передачу POST/FILES в сервисы. Контроллерные сценарии create/update с тегами покрыты в `tests/phpunit/integration/ItemsControllerTest.php`, а прямой сервисный тест `tests/phpunit/integration/ItemFormAssetServiceTest.php` проверяет сохранение тегов и реальное прикрепление JPEG-файла. Тестовый фото-конфиг в `tests/phpunit/config/common.php` использует реальные файловые пути через alias `@phpunitRuntime`, потому что `Photo::assignFile()` ожидает пути файловой системы, а не Yii alias.

Подготовка и сохранение предмета для форм создания/редактирования вынесены в `backend/services/ItemFormService.php` и `backend/models/ItemForm.php`. Сервис выставляет create/update scenario, access validator, repo/parent, createdBy/updatedBy и начальный флаг контейнера на `Item`, затем возвращает `ItemForm`. POST загружается в `ItemForm`, а ее `save()` валидирует строки и переносит типизированные значения в `Item`. Контракт покрыт `tests/phpunit/integration/ItemFormServiceTest.php`, а HTTP-сценарии create/update остаются защищены `tests/phpunit/integration/ItemsControllerTest.php`.

Read-часть `ItemsController::actionView()` и `ItemsController::actionJsonPreview()` вынесена в `backend/services/ItemViewDataService.php`. Сервис готовит дочерние предметы в порядке отображения, соседние предметы для prev/next навигации и path-данные для preview partial, а DTO `backend/services/ItemViewData.php` и `backend/services/ItemPreviewData.php` переносят эти данные обратно в контроллер. Контракт сервиса покрыт `tests/phpunit/integration/ItemViewDataServiceTest.php`, включая сортировку детей, соседние предметы и path URLs; HTTP-слой дополнительно проверяется render/json-preview сценариями в `tests/phpunit/integration/ItemsControllerTest.php`.

Create/update формы заметок вынесены из `PostsController` в `backend/services/PostFormService.php` и `backend/models/PostForm.php`. Сервис готовит create/update сценарии `Post`, служебные поля `createdBy`/`updatedBy`, возвращает `PostForm`, сохраняет ее и прикрепляет новые фотографии через `Photo` + `PostPhoto`. POST-дата `datetimeText` валидируется в `PostForm` и записывается в `Post.datetime` как unix timestamp. Контроллер оставляет за собой поиск repo/item/post, render и redirect. Удаление заметок вынесено в `backend/services/PostDeletionService.php`. Контроллерные сценарии create/update/delete покрыты в `tests/phpunit/integration/PostsControllerTest.php`, а прямые контракты сервисов - в `tests/phpunit/integration/PostFormServiceTest.php` и `tests/phpunit/integration/PostDeletionServiceTest.php`.

Управление существующими фотографиями предметов и заметок вынесено из `PhotoController` в `backend/services/PhotoAttachmentService.php`. Сервис поддерживает два типа связей, `ItemPhoto` и `PostPhoto`, сортирует фотографии внутри правильного списка и удаляет нужную связь. Форма предмета и форма заметки передают в AJAX явный `photoType` (`item` или `post`), а `backend/web/js/upload_photo.js` отправляет этот тип вместе с ID связи. Это исправляет прежний дефект, когда сортировка и удаление фотографий работали у предметов, но не работали у заметок. Поведение покрыто `tests/phpunit/integration/PhotoAttachmentServiceTest.php`, `tests/phpunit/integration/PhotoControllerTest.php` и render-проверкой формы заметки в `tests/phpunit/integration/PostsControllerTest.php`.

Подготовка create/update форм репозитория вынесена из `RepoController` в `backend/services/RepoFormService.php`. Сервис готовит `RepoForm` для создания и редактирования, заполняет update-форму текущими значениями `Repo` + `RepoUser` и делегирует сохранение самой форме. `RepoForm::save()` валидирует строковые поля и переносит значения в `Repo`/`RepoUser` прямым присваиванием, без `load()` у AR. Удаление репозитория и расчет `affectedUsers` вынесены в `backend/services/RepoDeletionService.php`. Контроллерные сценарии create/update/delete покрыты в `tests/phpunit/integration/RepoControllerTest.php`, а прямые контракты сервисов - в `tests/phpunit/integration/RepoFormServiceTest.php` и `tests/phpunit/integration/RepoDeletionServiceTest.php`.

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
- Psalm 6.16 через `psalm/phar`, текущий `errorLevel="2"`, конфиг `psalm.xml`. PHAR выбран потому, что обычный пакет `vimeo/psalm` в актуальных версиях конфликтует с PHPUnit 13 по `sebastian/diff`, а старые версии Psalm не подходят для текущего PHP 8.4-стека.
- PHPCS 3.13 - `phpcs.xml` проверяет PSR-12 на активно рефакторимом backend-контуре (`backend/controllers`, `backend/services`, `common/services`, `tests/static-analysis`), а `phpcs-compat.xml` отдельно прогоняет PHPCompatibility по широкому дереву приложения.

Psalm настроен без baseline. В конфиге подавлен типичный шум Yii/PHPUnit: route action методы и тестовые классы как unused, требование `#[Override]`, шаблонные параметры Yii-классов и Yii view-контекст. View-файлы не анализируются Psalm как обычные PHP-классы, потому что в них `$this` и переданные переменные живут в контексте шаблона.

Для Psalm добавлен отдельный stub `tests/static-analysis/psalm-yii-stubs.php`. Он не участвует в runtime-загрузке приложения, а только связывает generic-шаблон Yii `BaseYii<TUserIdentity>` с проектной моделью `common\models\User`. Благодаря этому Psalm корректно видит `Yii::$app->getUser()` как `yii\web\User<common\models\User>`, а сервисные phpdoc-сигнатуры с пользователем тоже указывают `yii\web\User<common\models\User>`. Для анализа основного кода `Yii::$app` намеренно типизирован как уже поднятое `yii\web\Application<common\models\User>`: bootstrap статического анализа создает web-приложение, а nullable-вариант дает много ложного шума на `errorLevel="3"`.

PHPStan использует bootstrap `tests/static-analysis/bootstrap.php`, который подключает Yii и выставляет project aliases. Кэши PHPStan/Psalm пишутся в `/tmp` внутри контейнера, чтобы не зависеть от прав на `tests/phpunit/_runtime`.

View/mail-шаблоны стоит аннотировать через `/** @var Type $variable */`, а не старым Yii-стилем `/* @var $variable Type */`: это помогает и IDE, и PHPStan. Для `$this` в шаблонах тоже работает явная строка `/** @var \yii\web\View $this */`; отдельный `ignoreErrors` для view-шаблонов не нужен.

При переходе PHPStan на `level: 3` query-классы `common/models/*Query.php` приведены к generics Yii 2.0.55 через `@extends ActiveQuery<Model>`. Старые Gii-заглушки `all()` и `one()`, которые просто вызывали parent-методы и мешали выводу типов, удалены. Это не меняет runtime-поведение, потому что методы наследуются от Yii `ActiveQuery`.

При переходе Psalm на `errorLevel="5"` query-классы дополнительно сделаны generic-обертками над `ActiveQuery<TModel>`, а `ActiveRecord::find()` в моделях документирован как `@return SomeQuery<static>`. Это согласует кастомные query-классы с Yii 2.0.55, где базовый `ActiveRecord::find()` возвращает `ActiveQuery<static>`. Также явно обработаны `false|null` от GD/буферных функций в `ImageResize` и от `preg_replace_callback()` в `MarkdownFormatter`.

При переходе Psalm на `errorLevel="4"` исправлены оставшиеся строгие места: ленивое поле пользователя в `ItemAccessValidator` стало nullable, неявные scalar-приведения в фото/thumbnail console-коде сделаны явными, Yii `ColumnSchemaBuilder` в миграциях явно приводится к строке перед `addColumn()`/`alterColumn()`, а намеренный `shell_exec()` в console-команде запроса пароля подавлен локальным `@psalm-suppress ForbiddenCode`.

При переходе Psalm на `errorLevel="3"` основные исправления были вокруг потенциальных `null|false` и слишком широких типов Yii API. POST-данные для `Model::load()` теперь нормализуются через `common\helpers\PostDataHelper::toArray()`, в который явно передается сырой результат `Yii::$app->request->post()`. `LoginForm::login()` явно проверяет найденного пользователя перед `Yii::$app->user->login()`, `Photo` и console-код обрабатывают `tempnam()`, `imagejpeg()`, `shell_exec()`, `preg_split()` и похожие функции, а `ItemQuery::notDeleted()`/`onlyDeleted()` документированы как fluent-методы `@return $this`, чтобы Psalm не терял generic тип `ItemQuery<static>`. В Psalm stub также уточнены relation-методы `yii\db\ActiveRecord::hasOne()`/`hasMany()` до concrete `ActiveQuery`, что соответствует runtime-поведению Yii и убирает ложные ошибки на model relations.

В `RepoAwareController::getLoggedUser()` оставлена локальная `@phpstan-var` вместе с `@psalm-suppress UnnecessaryVarAnnotation`: Psalm получает generic-тип пользователя из своего Yii stub, а PHPStan на текущей конфигурации не выводит `yii\web\User<common\models\User>` из `Yii::$app->getUser()` без этой подсказки.

Relations в `common/models` типизированы через `@return ActiveQuery<Model>`, а Psalm stub для `ActiveRecord::hasOne()`/`hasMany()` дополнительно связывает `class-string<TModel>` с `ActiveQuery<TModel>`. Это позволяет Psalm/PHPStan выводить типы после `one()` и `all()` без локальных `/** @var Model|null $model */` и `/** @var Model[] $models */`. Когда добавляются новые relation generics, лишние локальные подсказки удобно выявлять обычным `composer run psalm`: Psalm поднимает их как `UnnecessaryVarAnnotation`.

При переходе Psalm на `errorLevel="2"` в конфиге оставлены suppress для `ClassMustBeFinal`, `PropertyNotSetInConstructor` и `RedundantCastGivenDocblockType`. Для Yii-приложения эти категории дают много низкополезного шума: контроллеры/модели остаются потенциальными framework extension points, ActiveQuery/миграции получают часть состояния от Yii, а защитные casts в тестах и AR-коде часто нужны из-за runtime-гидрации, несмотря на docblock-типы. Остальные level 2 замечания были исправлены кодом: добавлены PHPDoc-типы public form properties, явные strict comparisons, return/param/class-const types в console-коде, замена deprecated `InvalidParamException` и `Controller::EXIT_CODE_*`, а nullable `Item::itemId` теперь явно приводится к int при построении URL/path DTO и в тестовых вызовах action-методов.

## Git и локальные файлы

В корневом `.gitignore` намеренно игнорируются:

- `/docker-compose.yml`
- `/db_data/`
- `/logs/`
- локальные nginx-файлы в `docker/nginx/`
- локальный PHP-конфиг `docker/php/custom.ini`
- служебные каталоги `.agents/` и `.codex`

`docker-compose.yml.example`, `docker/php/custom.ini.example` и
`docker/nginx/default.conf.example` являются переносимыми примерами и должны
оставаться в Git. Реальный игнорируемый `docker/nginx/default.conf` нужно
синхронизировать с example при deploy; в частности, нельзя потерять отдельный
exact location с безопасной политикой error log для OIDC callback.

Если после перестройки структуры в индексе снова появится старый `app/docker-compose.yml`, это, скорее всего, след от прежней структуры проекта. Его не нужно коммитить; compose-файл проекта теперь находится в корне.

## Проверенное состояние

После переноса корня репозитория приложение успешно запускалось из корня проекта через Docker Compose. Контейнеры `stockhub-db`, `stockhub-nginx` и `stockhub-php` поднимались, `stockhub.lc` отвечал редиректом на страницу входа, Yii видел историю миграций, а новых миграций к применению не было.

`composer validate` проходит без ошибок. Ранее предупреждения о незафиксированных версиях (`*`) у `yiisoft/yii2-swiftmailer` и `kartik-v/yii2-widget-datetimepicker` были закрыты явными ограничениями. Yii обновлен до ветки `2.0.55`, PHPUnit - до `13.2`. `requirements.php` не находил критических ошибок, но предупреждал об отсутствующих/необязательных компонентах вроде `intl/ICU`, `pcntl`, `memcache/APC` и `ImageMagick`; `expose_php` был отключен отдельно.

## Авторизация через Pyrda SSO

Приложение поддерживает два независимых способа входа.
`STOCKHUB_PASSWORD_LOGIN_ENABLED=1` включает форму и обработку входа по паролю,
а `STOCKHUB_SSO_LOGIN_ENABLED=1` — кнопку, redirect и callback Pyrda SSO. Можно
включить любой один способ, оба сразу или ни одного. По умолчанию парольный вход
включён, а SSO выключен, поэтому установка без OIDC-конфигурации продолжает
работать. Если оба флага равны `0`, страница входа не предлагает ни одного способа
авторизации.

Выключение парольного входа также отключает обработку remember-me identity
cookies. Уже открытые серверные сессии при SSO-only переключении нужно отозвать
отдельной операционной процедурой. Явный logout всегда истекает identity cookie,
даже когда auto-login уже выключен. Перед таким переключением нужно также массово
ротировать `User.authKey`: это отзовет cookies пользователей, которые не выполняли
logout, и не позволит им снова активироваться при возврате парольного входа.
Такая массовая ротация пока намеренно не реализована.

Backend web bootstrap читает `YII_ENV` и `YII_DEBUG` непосредственно из
environment, по умолчанию использует `prod`/`0` и отказывается запускать
сочетание `prod`/`1`. Yii Debug намеренно не подключается даже в dev-профиле:
его request snapshots сохраняют cookies, callback-параметры и environment
процесса. При явных `YII_ENV=dev` и `YII_DEBUG=1` доступен только Gii.
PHP-FPM сохраняет исторический UID/GID `33`: существующие каталоги фотографий
уже принадлежат этому пользователю, и массово менять владельца нескольких
гигабайт пользовательских файлов нельзя ради логов. Вместо этого
`backend/runtime` вынесен в Docker volume `backend_runtime`, который
инициализируется из образа с владельцем `www-data` и режимом `0775`. Реальный
FileTarget после recreate проверен записью в `backend/runtime/logs/app.log`.
Для console-приложения используется отдельный volume `console_runtime`;
его FileTarget не добавляет globals и prefix, поэтому унаследованный
`OIDC_CLIENT_SECRET` не попадает в console-лог.
В production (`YII_ENV=prod`) session cookie `PHPSESSID` и remember-me cookie
`_identity` всегда имеют `Secure`, `HttpOnly` и `SameSite=Lax`. В dev `Secure`
намеренно выключен, чтобы локальный HTTP-контур оставался рабочим. Флаг
определяется непосредственно стандартной константой Yii `YII_ENV_PROD`;
отдельного env-нормализатора для этого нет. Поскольку tracked compose сохраняет
безопасный default `YII_ENV=prod`, для локальной работы через
`http://stockhub.lc` в игнорируемом compose нужно явно задать `YII_ENV=dev`.

OIDC-конфигурация читается в `common/config/params.php` из переменных
`OIDC_ISSUER`, `OIDC_CLIENT_ID`, `OIDC_CLIENT_SECRET`, `OIDC_REDIRECT_URI`,
`OIDC_SCOPES`, `OIDC_HTTP_TIMEOUT`, `OIDC_CLOCK_SKEW_SECONDS` и
`STOCKHUB_CANONICAL_ORIGIN`. `TRUSTED_PROXIES` задаёт comma-separated список
IP/CIDR непосредственного reverse proxy. Эти параметры требуются только при
включённом `STOCKHUB_SSO_LOGIN_ENABLED`. Canonical origin по умолчанию выводится
из `OIDC_REDIRECT_URI`; если задан отдельно, его полный origin (scheme, hostname
и port) обязан точно совпадать с callback. В tracked `params.php` нет значений
URL по умолчанию: issuer и callback обязаны приходить из окружения. Локальные
адреса задаются только в Docker Compose окружении разработки. Scopes по
умолчанию — `openid profile email`, HTTP timeout — 10 секунд, допустимое
расхождение часов — 60 секунд. У client ID и client secret также нет значений
по умолчанию: их нужно заполнить после регистрации отдельного клиента Stockhub
в SSO. Строковые значения OIDC-конфигурации с внешними пробелами считаются
ошибкой и не нормализуются молча. Authorization request и token exchange берут
client ID, redirect URI и scopes из одного проверенного snapshot
`OidcConfiguration`; исходные Yii params контроллер повторно не читает.

За TLS-terminating proxy нужно задать `TRUSTED_PROXIES` точным адресом или CIDR
сети этого proxy. Только для этих адресов Yii учитывает `X-Forwarded-For`,
`X-Forwarded-Host`, `X-Forwarded-Proto` и `X-Forwarded-Port`. Последний
дополнительно валидируется приложением как decimal port `1..65535`, потому что
Yii не добавляет его к `hostInfo`, когда уже присутствует Host. Пустое значение
fail-closed и не позволяет клиенту подделать origin, но при canonical HTTPS за
proxy приведёт к циклу HTTPS-redirect, а все клиенты будут делить IP-лимит
самого proxy. Нельзя использовать всеобщие диапазоны вроде `0.0.0.0/0`.
Статический IP proxy безопаснее CIDR общей Docker-сети; если используется CIDR,
нужно учитывать, что доверие получают все подключённые к ней контейнеры.
Внешний proxy обязан формировать корректную цепочку `X-Forwarded-For`, добавляя
реальный адрес HTTP-клиента, а не пропускать присланный клиентом заголовок как есть.

Один deployment публикует только один canonical hostname. Compose использует
`STOCKHUB_VIRTUAL_HOST` с default `stockhub.lc`; прежние `p.stockhub.ru` и
`k.stockhub.ru` больше не публикуются автоматически. Bootstrap-компонент
`CanonicalHostRedirect` при включённом SSO дополнительно возвращает `308` на
фиксированный canonical origin до инициализации `user` или `session`, если alias
всё-таки маршрутизирован в приложение. При выключенном SSO этот bootstrap-компонент
не запускается. Общая cookie с `Domain=.stockhub.ru` намеренно не используется.

Для локальной разработки PHP-контейнер получает
`sso.pyrda.lc:host-gateway` через `extra_hosts`. Поэтому server-to-server OIDC
запросы идут на reverse proxy хоста, а браузер продолжает открывать тот же issuer
по локальному домену.

Реализация использует Authorization Code Flow с `state`, `nonce` и PKCE S256.
Подпись `id_token` проверяется по JWKS; разрешен только RS256, дополнительно
проверяются issuer, audience/authorized party и временные claims. До пяти
параллельных browser flows хранятся отдельно по `state` в течение двадцати минут;
неизвестный callback не уничтожает flows других вкладок. Запуск authorization
flow, включая исходящий discovery, защищён fail-closed limiter: не более десяти
запусков за 60 секунд на доверенно определённый client IP и не более сорока на
deployment. Callback discovery возможен только для одноразового pending state,
созданного таким запуском. После проверки одноразового state и наличия code,
но до первого callback discovery, атомарно резервируется более строгая callback
quota: два callback на client IP и восемь на deployment. Поэтому заранее
накопленные state не позволяют burst-ом занять PHP-FPM workers discovery-
запросами, а смена PHP session cookie не сбрасывает IP-квоту. Один reservation
покрывает последовательные discovery и `/oauth/token` и живёт
`60 + 2 * OIDC_HTTP_TIMEOUT` секунд, поэтому локальное окно не освобождается
раньше upstream-лимита Pyrda 10/min даже при двух задержанных соединениях.

Состояние limiter хранится в versioned JSON под `backend_runtime`: постоянный
lock-файл синхронизирует FPM workers, а новый state записывается во временный
файл с `fflush`/`fsync` и заменяется атомарным rename. Пустой или повреждённый
существующий state даёт 503 вместо сброса квоты. Такая реализация рассчитана на
один PHP deployment с общим `backend_runtime`; при горизонтальном запуске с
независимыми volumes нужен общий atomic limiter в Redis или БД.

Первый вход разрешен только для уже существующего активного пользователя
Stockhub, которого администратор заранее связал с точной парой `(issuer, sub)`
командой `./yii user/link-sso <username-or-email> <subject>`. Issuer перед
сохранением и в web-runtime приводится к одному виду без завершающего `/`.
Автоматическая привязка по email запрещена: текущий Pyrda SSO позволяет владельцу
профиля менять email, поэтому такой email не является безопасным основанием для
передачи локальных прав. Claims `email` и `email_verified` не обязательны:
`openid`-only token достаточен, потому что email не участвует в поиске или
привязке. `ssoIssuer` и `ssoSubject` хранятся как `VARBINARY(255)`, сравниваются
побайтно (включая регистр и завершающие пробелы) и защищены составным unique-index
и CHECK-инвариантом. В текущей реализации Pyrda SSO значение `subject` равно
строковому ID пользователя из `php artisan sso:user:list`. Новые локальные
пользователи не создаются, а существующие пароль, auth key и права доступа не
изменяются.

Локальный confidential-клиент `StockHub` зарегистрирован в Pyrda SSO с callback
`http://stockhub.lc/auth/sso/callback`; его credentials находятся только в
игнорируемом `docker-compose.yml`, а текущий локальный пользователь уже
административно связан со своим SSO subject. Profile/access webhooks для клиента
намеренно не настроены: их реализация отложена на отдельный этап. До этого отзыв
доступа в SSO не завершает уже открытую локальную сессию Stockhub; новый
OIDC-вход при отозванном доступе SSO уже не разрешит.

FileTarget backend-приложения не пишет request/session/server globals и не
включает session ID в prefix. Это обязательная часть OIDC-конфигурации: иначе
PHP-FPM environment с `OIDC_CLIENT_SECRET`, callback code и cookies попадали бы
в лог при штатной ошибке авторизации. Внутренний nginx Stockhub также использует
access-log format без query string и Referer. Стандартный Nginx error log
санитизировать по полям нельзя: ошибки 413, timeout или недоступный upstream
включают полный request URI. Поэтому оба proxy-слоя имеют отдельный exact
`location = /auth/sso/callback` с `error_log /dev/null crit` и лимитом тела
`1k` (OIDC использует `response_mode=query`, тело не требуется). Внутренний
location вызывает `index.php` напрямую через FastCGI, иначе `try_files` сделал
бы internal redirect в generic PHP location с обычным error log. Для остальных
маршрутов штатная Nginx-диагностика сохранена; callback status и path остаются
видны в безопасном access log, а ошибки приложения — в очищенном Yii FileTarget.
Корневой `.dockerignore` исключает из build context локальный compose и конфиги,
credentials/certificates, БД, логи/runtime, фотографии, thumbnails, assets,
vendor и служебные каталоги.

Соседний локальный checkout общего proxy не является частью Stockhub и не
является source of truth для домашнего production deployment. Tracked шаблон
домашнего внешнего nginx находится в
`deploy/nginx-proxy/stockhub.ru.conf.example`; фактический production-файл —
`/home/gugglegum/nginx-proxy/conf.d/stockhub.ru.conf`. Шаблон не применяется
автоматически: перед deploy нужно сравнить его с текущим конфигом и проверить
пути сертификата, ACME webroot и доступность upstream `stockhub-nginx`.

На 2026-08-11 SSO rollout применён на production. Stockhub работает на
`master`/`b4d362f`, а Pyrda SSO — на `master`/`88fa7fb`. Production-клиент
`StockHub` зарегистрирован как confidential с callback
`https://stockhub.ru/auth/sso/callback`, homepage `https://stockhub.ru` и portal
order `60`; profile/access webhooks намеренно выключены. Credentials хранятся
вне репозитория в `/home/gugglegum/.config/stockhub/production.env` с режимом
`0600`. Включены оба независимых способа входа: пароль и SSO. Локальный
пользователь `gugglegum` явно связан с `(https://sso.pyrda.ru, 1)`; остальные
пользователи автоматически по email не связывались.

Production compose задаёт `YII_ENV=prod`, `YII_DEBUG=0`,
`TRUSTED_PROXIES=172.18.0.0/16`, canonical origin `https://stockhub.ru`, HTTPS
issuer/callback и отдельные `backend_runtime`/`console_runtime` volumes. Оба
runtime доступны `www-data`, secret не найден в runtime-логах. Миграция
`m260727_034500_add_sso_fields_to_user` применена. Реальные inner/edge Nginx
конфиги синхронизированы с tracked templates; HTTP возвращает `308` до
приложения, HTTPS выдаёт HSTS, Basic Auth удалён, а `p.stockhub.ru`
канонизируется на `https://stockhub.ru`. Alias `k.stockhub.ru` намеренно не
входит в текущий рабочий контур и указывает на другой IP.

Во время rollout принудительная callback-ошибка `413` проверена отдельно на
внешнем и внутреннем Nginx. Уникальные dummy `code`/`state`/Referer markers не
попали в их access/error logs, а безопасные access rows содержали только path.
Внешний `/auth/sso/redirect` вернул `302` на production Pyrda SSO с PKCE,
callback без pending state — ожидаемый Yii `419`. Полный вход с паролем и TOTP
требует завершающего ручного browser smoke владельцем учётной записи.

Текущий `nginx-proxy` подключён к `proxy-network` с динамическим адресом
(при проверке был `172.18.0.8`). Нельзя навсегда записывать этот адрес в
`TRUSTED_PROXIES` без статического IPAM: после recreate он может измениться.
Предпочтительный rollout — сначала закрепить адрес proxy и доверять только ему;
альтернатива `172.18.0.0/16` проще, но осознанно доверяет forwarded-заголовки
всех контейнеров общей сети.

Deployment checklist внешнего proxy:

1. HTTP location `/.well-known/acme-challenge/` должен остаться доступным, а
   любой другой HTTP-запрос к canonical host или alias должен получить `308` на
   `https://stockhub.ru` без `WWW-Authenticate`. Так Basic Auth никогда не
   запрашивает credentials по открытому HTTP.
2. HTTPS-ответы canonical host, включая `401`, должны содержать
   `Strict-Transport-Security: max-age=31536000`; `p.stockhub.ru` и
   `k.stockhub.ru` должны отвечать `308` на canonical HTTPS host.
3. Canonical proxy обязан задавать фиксированные `Host`/`X-Forwarded-Host`,
   `X-Forwarded-Proto=https`, `X-Forwarded-Port=443` и формировать
   `X-Forwarded-For` из реального адреса клиента. Значение `TRUSTED_PROXIES`
   приложения должно точно доверять адресу или сети этого proxy.
4. Stockhub access-log должен использовать sanitized format с method, URI path
   и protocol, без `$request`, `$args`, `$query_string` и Referer. Exact callback
   location на внешнем и внутреннем слоях должен иметь локальный
   `error_log /dev/null crit`; обычные маршруты продолжают наследовать штатный
   file-backed error log.
5. После `nginx -t` и graceful reload нужно принудительно получить Nginx-ошибку,
   а не только штатный Yii 419. Отправить callback с телом больше `1k`,
   уникальными marker в `code`/`state` и отдельным marker в `Referer`: внешний
   запрос должен вернуть 413. Затем повторить запрос напрямую к
   `stockhub-nginx`, чтобы отдельно проверить внутренний слой. Marker не должны
   находиться ни в access-, ни в error-логах; строка безопасного access log
   должна содержать path `/auth/sso/callback` без query и Referer наряду с
   настроенными несекретными полями вроде method, protocol, status, time и
   размера ответа.
6. В изолированном pre-deploy smoke нужно также направить callback в недоступный
   proxy/FastCGI upstream и проверить 502/timeout. Контрольный non-callback 502
   обязан остаться в обычном error log — это подтверждает, что диагностика не
   отключена для всего vhost. Только после этих проверок защиту callback можно
   считать применённой на production.

2026-07-28 оба tracked-конфига проверены именно таким изолированным smoke:
callback 413 и 502 не сохранили уникальные `code`, `state` и Referer markers
ни на одном слое; контрольный non-callback 502 остался в обычном error log.
Дополнительно внутренний direct FastCGI с реальным `stockhub-php` вернул
ожидаемый Yii 419 `Invalid OIDC state`, то есть exact location не сломал routing.
