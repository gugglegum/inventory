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

Миниатюры работают как кэш. Новый async photo editor
явно удаляет все найденные thumbnails при отмене временной загрузки
и при отсоединении `Photo` через форму. Другие исторические пути
удаления могут по-прежнему оставить cache files в `app/thumbnails/`;
автоматически искать и удалять такие исторические orphan-файлы нельзя.

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

При deployment защищенной отдачи нужно синхронизировать tracked
`docker/nginx/default.conf.example` с фактическим игнорируемым
`docker/nginx/default.conf`, выполнить `nginx -t` и graceful reload. Обновление
PHP без одновременного обновления nginx приведет к 404 на новых внутренних URI,
а сохранение старых публичных `location /photos` и `/thumbnails` оставит обход
авторизации. Защищенная отдача развернута на production 2026-08-11;
ее internal locations также переиспользуются для preview временных загрузок.

## Асинхронная загрузка и редактирование фотографий

Формы предмета и заметки используют один shared editor:

- view `backend/views/_photo-editor.php`;
- client controller `backend/web/js/photo-editor.js` и стили
  `backend/web/css/photo-editor.css`;
- form model `backend/models/PhotoEditorForm.php`;
- HTTP API `backend/controllers/PhotoUploadController.php`;
- lifecycle-сервисы `backend/services/PhotoUploadService.php` и
  `backend/services/PhotoEditorService.php`.

В формах больше нет синхронного `input type=file` с массивом
`photos[]`. По клику на droparea открывается multiple file picker,
файлы можно перетаскивать на droparea или вставлять из clipboard.
Глобальный paste-listener не зависит от focus: если на странице один
видимый editor, он получит clipboard image; при нескольких
видимых editor-ах используется последний активированный.

Каждый файл отправляется отдельным XHR с собственным progress.
Сессия создается лениво перед первым корректным файлом. Клиент
держит не более трех concurrent uploads, ставит остальные в очередь
и показывает статусы `queued`, `uploading`, `ready` и `error`.
Файл можно повторить после network/server error. Submit родительской формы
заблокирован, пока есть queued/uploading/error карточка или активный
DELETE временной карточки.

Готовая карточка сразу получает thumbnail и protected preview.
Прежние и новые карточки сортируются одним drag handle: визуальная копия
следует за pointer, исходная карточка занимает место placeholder, а соседние
карточки перестраиваются с короткой FLIP-анимацией. С клавиатуры порядок
меняется стрелками на focused handle. Клик по карточке
открывает общую Fancybox 2-галерею с явным `type: image`, поэтому
preview работает и для URL без `.jpg`.

HTTP API состоит из routes `photo-upload/create`, `photo-upload/state`,
`photo-upload/upload`, `photo-upload/view`, `photo-upload/thumbnail` и
`photo-upload/delete`. Все routes требуют авторизацию; для каждого
запроса перепроверяются owner, `repoId`, server-whitelisted `context`
и текущее право `RepoUser`. Контексты: `item-create`, `item-update`
и `post`; они требуют соответственно `ACCESS_CREATE_ITEMS`,
`ACCESS_EDIT_ITEMS` и наличие `RepoUser`. Отзыв доступа к репозиторию
немедленно закрывает state/upload/preview уже созданной сессии.
Токен — 64 lowercase hex characters и сравнивается в БД побайтно, но
знание токена не заменяет session authentication и owner/access checks.

Оригинал и thumbnail временной фотографии отдаются через
`PhotoDeliveryService` и те же nginx `internal` locations
`/_protected-photos/` / `/_protected-thumbnails/`, что и у обычных
фотографий. PHP проверяет доступ и возвращает `X-Accel-Redirect`,
а nginx отдает body; прямой запрос к internal URI остается 404.

### Manifest, revision и семантика save

`PhotoEditorForm` отправляет вместе с формой три hidden-поля:

- `sessionToken` — owner-bound upload-сессия;
- `manifest` — единый ordered JSON list вида
  `[{"type":"existing","id":12},{"type":"temporary","id":34}]`;
- `revision` — SHA-256 от текущих ID связей и `sortIndex`, полученный
  при открытии формы и не меняющийся на клиенте.

ID `existing` — это ID связи `ItemPhoto`/`PostPhoto`, а не `Photo.id`;
ID `temporary` — `PhotoUploadFile.id`. Порядок manifest становится
итоговым `sortIndex` от нуля. Манифест содержит не более 500
уникальных записей и валидируется server-side.

До сохранения формы удаление и перестановка прежних
фотографий меняют только manifest в browser. Кнопка remove для уже
загруженной временной карточки может удалить ее сразу: это еще не
пользовательская привязка, а temporary marker. Если DELETE не удался,
карточка исчезает из UI, а marker подберет TTL cleanup.

При submit `PhotoEditorService` внутри транзакции родительской формы:

1. блокирует current `ItemPhoto`/`PostPhoto` и upload rows через
   `SELECT ... FOR UPDATE`;
2. проверяет immutable revision, owner/repo/context сессии и что все
   IDs manifest принадлежат текущей форме;
3. сохраняет Item/Post и остальные поля формы;
4. создает `ItemPhoto`/`PostPhoto` для temporary entries, переставляет
   все связи и удаляет опущенные existing связи;
5. удаляет markers примененных загрузок и помечает пустую
   сессию `consumedAt`;
6. commit-ит все DB-изменения вместе.

Перед установкой финальных sortIndex связи временно переносятся
в отрицательный диапазон, чтобы не нарушить соответствующий unique index
`(itemId, sortIndex)` или `(postId, sortIndex)`. Revision mismatch при параллельном редактировании
не перезатирает чужой список, а возвращает ошибку с требованием
обновить страницу.

Если основная форма не прошла валидацию или транзакция
откатилась, ни порядок, ни удаление existing-фотографий, ни
привязка temporary-фотографий не применяются. Последние остаются
доступны в повторно отрисованной форме до истечения TTL.

### Temporary markers, 24-hour TTL и безопасная очистка

Успешный upload сразу создает обычную `Photo`, физический JPEG в
`app/photos/` и thumbnail cache в `app/thumbnails/`. Отдельной папки
с ожидающими finalize файлами нет. Временность задается
исключительно явной строкой `photo_upload_file`, связывающей
`photo_upload_session` с `Photo`. Пока marker существует, `Photo` не считается
примененной к предмету/заметке.

Сессия привязана к user, repo и context, имеет `expiresAt` и
`consumedAt`. TTL равен 24 часам и продлевается от каждого успешного
upload, поэтому файл из давно открытой формы не будет удален раньше
чем через 24 часа после последней успешной активности. При удалении
пользователя или репозитория их upload-сессии удаляются явно до
hard delete, потому что FK сами по себе не могут безопасно удалить
физические files.

Удаление existing-фотографии формой не удаляет JPEG до commit.
В той же DB-транзакции, где удаляется `ItemPhoto`/`PostPhoto`,
создается явный marker `photo_deletion_queue`. После commit сервис
пытается сразу удалить thumbnails, JPEG, `Photo` и queue marker.
Если post-commit filesystem cleanup завершился ошибкой, queue marker
остается для повтора hourly cron. Если такая `Photo` к моменту cleanup
снова привязана, cleanup удалит только queue marker и сохранит данные.

Критический safety invariant: `photo-uploads/prune` и любая ручная
очистка этой подсистемы могут трогать `Photo` только при наличии
явного `photo_upload_file` или `photo_deletion_queue`. Cron никогда не
должен искать «все Photo без ItemPhoto/PostPhoto» и никогда не должен
удалять unmarked historical orphans. Это отдельные данные, которые могут
происходить из старых версий проекта.

Команда только показывает кандидатов при явном Yii bool option
`--dry-run=1`:

```bash
cd /home/gugglegum/stockhub.ru
docker compose exec -T php ./yii photo-uploads/prune --dry-run=1
```

Реальная очистка:

```bash
cd /home/gugglegum/stockhub.ru
docker compose exec -T php ./yii photo-uploads/prune
```

В production нет и не нужен отдельный scheduler-container. Команду раз в час
запускает cron хоста от пользователя, имеющего доступ к Docker. Пока достаточно
простого crontab; позднее его можно заменить общим runner-ом с lock, timeout и
централизованным логированием. Пути `docker` и checkout нужно предварительно
сверить на самом сервере:

```cron
17 * * * * cd /home/gugglegum/stockhub.ru && /usr/bin/docker compose exec -T php ./yii photo-uploads/prune >> logs/photo-uploads-prune.log 2>&1
```

Перед установкой cron на production обязателен ручной dry run. Первый
реальный запуск также нужно проконтролировать по выводу и логу.

### Форматы, нормализация и лимиты

Сервер принимает только фактически распознанные `getimagesize()`
GIF, JPEG, PNG и WebP. Расширение и client MIME не являются границей
доверия. `Photo::assignFile()` полностью декодирует source и всегда
сохраняет нормализованный JPEG; и `Photo`, и protected preview поэтому
всегда указывают на JPEG. Thumbnail 320x320 с параметрами
`upscale=false`, `crop=false`, quality 90 создается сразу после upload; если
предварительная генерация не удалась, thumbnail endpoint повторяет ее
при первом GET.

Прикладные лимиты задаются в `common/config/params.php` внутри
`photos`:

- `maxUploadBytes = 50 * 1024 * 1024` — 50 MiB на файл;
- `maxUploadPixels = 60_000_000` — 60 млн пикселей после чтения заголовка;
- `maxFilesPerUploadSession = 100`;
- `maxTemporaryFilesPerUser = 300` во всех его сессиях;
- `maxOpenUploadSessionsPerUser = 20`.

Клиент повторяет проверку формата и 50 MiB для UX, но
авторитетны только server checks. Нулевой лимит в params отключает
соответствующее ограничение. Transport-лимиты сейчас выше: PHP
`upload_max_filesize`/`post_max_size` и inner/edge nginx `client_max_body_size`
равны 512M. При изменении `maxUploadBytes` нужно отдельно
сверять оба nginx-слоя и PHP ini.

### Миграция, deployment и checklist

Миграция `m260811_120000_create_photo_upload_tables`:

- исправляет исторический индекс `post_photo`: нормализует `sortIndex`
  внутри каждой заметки и создает unique index `(postId, sortIndex)`;
- создает `photo_upload_session` с binary-comparable 64-char token,
  owner/repo/context, rolling expiry и consumed marker;
- создает `photo_upload_file`, где `photoId` уникален и FK к сессии/
  `Photo` имеют cascade semantics;
- создает rollback-safe `photo_deletion_queue` с уникальным `photoId`.

Порядок rollout:

1. сделать бэкап MariaDB и убедиться, что есть актуальная
   восстанавливаемая копия `app/photos/`; миграция меняет порядок
   `post_photo`, а новый code получает право физически удалять файлы только
   по explicit markers;
2. до rollout прогнать `app/bin/check-quality`, отдельно
   `git diff --check` и миграции с нуля на `stockhub_test`;
3. обновить tracked code на production и применить
   `docker compose exec -T php ./yii migrate --interactive=0`;
4. проверить наличие трех новых таблиц, `uq_photo_upload_session_token`,
   `ux_post_photo_postId_sortIndex` и историю миграций;
5. убедиться, что PHP-FPM может писать `app/photos/`,
   `app/thumbnails/` и их temp-подкаталоги; внутренний nginx должен
   читать файлы для `X-Accel-Redirect`;
6. пройти browser smoke для create/update предмета и create/update
   заметки: multi-select, drop, paste при focus в textarea, individual progress,
   retry/error, preview, drag/keyboard reorder, remove и то, что все эти изменения
   применяются только после Save;
7. отдельно проверить validation-error с повторным показом temporary cards,
   revision conflict и запрет preview без login/другому user/после отзыва
   RepoUser; прямые `/_protected-*` URL должны оставаться 404;
8. выполнить production `photo-uploads/prune --dry-run=1`, сверить
   счетчики с DB markers и только после этого установить hourly host cron;
9. после первого реального prune проверить счетчики, application log,
   наличие expected files и что unmarked historical orphans не изменились.

Для отката code не нужно автоматически запускать `safeDown()`:
новые таблицы не мешают старому runtime, а down migration меняет
уникальный индекс `post_photo`. Откат схемы требует отдельного
осознанного решения и бэкапа.

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

Сохранение связанных текстовых данных формы предмета вынесено из `ItemsController::actionCreate()` и `ItemsController::actionUpdate()` в `backend/services/ItemFormAssetService.php`. Сервис теперь сохраняет только теги через `ItemTagsForm`; синхронный разбор `$_FILES['photos']` удален. Единый `PhotoEditorService` готовит и применяет ordered manifest фотографий в той же DB-транзакции, что и Item/теги. Тестовый фото-конфиг в `tests/phpunit/config/common.php` использует реальные файловые пути через alias `@phpunitRuntime`, потому что `Photo::assignFile()` ожидает пути файловой системы, а не Yii alias.

Подготовка и сохранение предмета для форм создания/редактирования вынесены в `backend/services/ItemFormService.php` и `backend/models/ItemForm.php`. Сервис выставляет create/update scenario, access validator, repo/parent, createdBy/updatedBy и начальный флаг контейнера на `Item`, затем возвращает `ItemForm`. POST загружается в `ItemForm`, а ее `save()` валидирует строки и переносит типизированные значения в `Item`. Контракт покрыт `tests/phpunit/integration/ItemFormServiceTest.php`, а HTTP-сценарии create/update остаются защищены `tests/phpunit/integration/ItemsControllerTest.php`.

Read-часть `ItemsController::actionView()` и `ItemsController::actionJsonPreview()` вынесена в `backend/services/ItemViewDataService.php`. Сервис готовит дочерние предметы в порядке отображения, соседние предметы для prev/next навигации и path-данные для preview partial, а DTO `backend/services/ItemViewData.php` и `backend/services/ItemPreviewData.php` переносят эти данные обратно в контроллер. Контракт сервиса покрыт `tests/phpunit/integration/ItemViewDataServiceTest.php`, включая сортировку детей, соседние предметы и path URLs; HTTP-слой дополнительно проверяется render/json-preview сценариями в `tests/phpunit/integration/ItemsControllerTest.php`.

Create/update формы заметок вынесены из `PostsController` в `backend/services/PostFormService.php` и `backend/models/PostForm.php`. Сервис готовит create/update сценарии `Post`, служебные поля `createdBy`/`updatedBy` и сохраняет только данные заметки; синхронный `$_FILES` contract удален. POST-дата `datetimeText` валидируется в `PostForm` и записывается в `Post.datetime` как unix timestamp. Фотографии заметки применяются тем же shared `PhotoEditorService`, что и фотографии предмета, в одной транзакции с `Post`. Удаление заметок вынесено в `backend/services/PostDeletionService.php`.

Прежние immediate AJAX actions `photo/delete`, `photo/sort-up` и `photo/sort-down`, сервис `PhotoAttachmentService` и script `backend/web/js/upload_photo.js` удалены. Они меняли DB до сохранения родительской формы. Теперь shared `PhotoEditorService` одинаково обслуживает `ItemPhoto` и `PostPhoto`, а удаление и порядок existing-связей применяются только после успешного Save. Новые контракты покрыты `tests/phpunit/integration/PhotoUploadServiceTest.php`, `PhotoUploadControllerTest.php` и `PhotoEditorServiceTest.php`; controller/service-тесты Item/Post закрепляют интеграцию с основными формами.

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

Ручная проверка 2026-06-08 относилась к удаленному синхронному `photos[]` flow и не является acceptance-проверкой async editor. Для нового flow обязателен отдельный browser smoke из deployment checklist выше. Сложный сценарий удаления предмета-контейнера с вложенными контейнерами, предметами, заметками и фотографиями пока не проходил ручную проверку целиком.

## Поиск и дерево предметов

Дерево предметов остается adjacency list: глобальный `item.id` является PK,
изменяемый `item.itemId` уникален внутри репозитория, а
`item.parentItemId` ссылается на repo-scoped `itemId` родителя. В одном
репозитории может быть несколько корней; исторические данные также содержат
предметы с дочерними записями при `isContainer=0`, и обход дерева не должен
отсекать такие ветви.

Для ограничения поиска галочкой «Искать внутри» nested sets намеренно не
добавлялись. На 2026-08-11 production содержит около шести тысяч предметов с
максимальной глубиной 8, а MariaDB 11.6 поддерживает recursive CTE. Существующие
индексы `(repoId, itemId)` и `(repoId, parentItemId)` уже обеспечивают anchor и
переход к детям. Nested sets при этом потребовали бы обновлять множество границ
при обычном создании, пакетном импорте и массовых переносах во время закрытия
инвентаризации, а также централизовать и сериализовать все structural writes.

`backend/services/ItemSearchService.php` сначала строит recursive CTE из
выбранного контейнера и ограничивает им основной текстовый запрос. Поддерево
включает сам контейнер. Anchor и recursive branch выбирают только активные
записи; `UNION DISTINCT` по идентификаторам узлов также завершает обход при
случайном цикле в поврежденных данных. После фильтрации SQL возвращает не более
2001 строки: первые 2000 показываются, а последняя используется только для
флага усеченной выдачи.

Пути для хлебных крошек больше не загружаются через lazy `parentItem` для
каждого кандидата. Второй recursive CTE одним запросом получает объединение
найденных узлов и всех их активных предков, после чего пути собираются в PHP с
защитой от повторного узла. Поисковые запросы должны добавлять repo-условие
через `andWhere()`: обычный `where()` стирает default scope `deleted IS NULL`
из `Item::find()` и может вернуть мягко удаленную запись. Этот же инвариант
применяется к container picker в `ItemListService`.

Контракт покрыт `tests/phpunit/integration/ItemSearchServiceTest.php`: inclusive
subtree, глубокий потомок через предмет без container-флага, соседняя ветвь,
другой репозиторий, прямой поиск по itemId, soft delete, точная граница лимита
внутри поддерева и защитное завершение при цикле. Отдельно остается возможная
оптимизация отношений, которые шаблон `_items.php` читает для каждой строки
(фотографии, теги и непосредственные дети), и полнотекстового поиска вместо
`LIKE '%term%'`. Также parent-chain validation при конкурентных переносах пока
может гоняться; это самостоятельная write-side задача и не решается CTE или
nested sets автоматически.

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

Срок локальной авторизации задается
`STOCKHUB_AUTH_SESSION_DURATION_SECONDS`; по умолчанию это `15552000` секунд,
то есть 180 дней — столько же, сколько `SESSION_LIFETIME=259200` минут в
Pyrda-проектах. Одно значение одновременно задает lifetime cookie
`PHPSESSID`, серверный `session.gc_maxlifetime` через Yii `Session::timeout`,
duration SSO-входа и remember-me парольного входа. Таким образом срок
продлевается при использовании сайта, а не ограничивается прежними
браузерным cookie и 1440-секундной очисткой session-файлов. Когда
`enableAutoLogin` включен, успешный SSO-вход дополнительно выдает `_identity`
на тот же срок; в SSO-only режиме длительная `PHPSESSID` продолжает работать
без identity cookie. Явный logout уничтожает серверную сессию и истекает
identity cookie.

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

### Profile и access webhooks Pyrda SSO

Stockhub принимает подписанные события SSO на двух публичных POST endpoints:

- `/sso/profile-webhook` — `user.profile.updated`;
- `/sso/access-webhook` — `user.access.revoked`, `user.access.restored` и
  `user.sessions.revoked`.

Оба endpoint отключают только Yii CSRF-проверку и вместо нее требуют отдельный
HMAC secret. Проверяются `X-SSO-Event`, UUID в `X-SSO-Delivery`, timestamp с
допуском по умолчанию 300 секунд и `X-SSO-Signature`, вычисленная как
HMAC-SHA256 от `<timestamp>.<raw body>`. Значения `event_id`/`event_type` в JSON
должны совпадать с headers. Payload ограничен 64 KiB и не записывается в
приложенческий лог; после проверки он сохраняется в delivery-таблице для
идемпотентности и диагностики. Повторный `event_id` получает `204` без повторной
обработки. Неизвестный `sub` также получает `204` и не создает пользователя.

Получатель ищет пользователя только по точной паре `(OIDC_ISSUER, sub)`.
Profile webhook обновляет локальные `username`/`email` из
`preferred_username`/`email`, сливает `name`, email и username в `ssoClaims` и
применяет только более новую `profile_version`. Если новые username/email
конфликтуют с другой локальной записью, транзакция откатывается и возвращает
неуспешный ответ, чтобы SSO retry не потерял изменение.

Access и session versions хранятся независимо. Новый `user.access.revoked`
ставит `ssoDisabledAt` и ротирует `authKey`; на следующем запросе Yii отвергает
и файловую session, и remember-cookie, а парольный и SSO-вход блокируются.
`user.access.restored` очищает блокировку, но не авторизует пользователя.
`user.sessions.revoked` также ротирует `authKey`, не меняя disabled-флаг. Это
событие отправляется SSO только для явного `Logout everywhere`. Обычный logout
из Pyrda SSO завершает лишь текущую SSO-сессию, webhook не отправляет и
180-дневные сессии подключенных проектов не затрагивает.

Настройки получателя:

```env
SSO_PROFILE_WEBHOOK_SECRET=<отдельный secret не короче 32 байт>
SSO_PROFILE_WEBHOOK_TIMESTAMP_TOLERANCE_SECONDS=300
SSO_ACCESS_WEBHOOK_SECRET=<другой secret не короче 32 байт>
SSO_ACCESS_WEBHOOK_TIMESTAMP_TOLERANCE_SECONDS=300
```

Secrets не должны попадать в Git и должны храниться рядом с OIDC credentials в
production env-файле. После применения миграции
`m260812_120000_add_sso_webhooks` клиент StockHub на стороне SSO настраивается
командами:

```sh
php artisan sso:client:profile-webhook <client-id> \
  https://stockhub.ru/sso/profile-webhook --secret=<profile-secret>
php artisan sso:client:access-webhook <client-id> \
  https://stockhub.ru/sso/access-webhook --secret=<access-secret>
```

Команды нужно выполнять внутри SSO PHP-контейнера от его штатного пользователя.
При rollout сначала разворачиваются миграция, endpoints и receiver secrets, и
только после успешной проверки endpoints включается отправка у клиента SSO.
Иначе outbox будет накапливать заведомо неуспешные delivery. Изменение env
Stockhub требует recreate PHP-контейнера; одной замены bind-mounted PHP здесь
недостаточно.

На 2026-08-12 локальный клиент StockHub уже настроен на оба URL
`http://stockhub.lc/sso/*-webhook`. Отдельные dev-only secrets находятся только
в игнорируемом `stockhub.ru/docker-compose.yml`; в игнорируемый compose SSO для
PHP и scheduler добавлен `stockhub.lc:host-gateway`. SSO scheduler запущен.
Подписанные smoke events с неизвестным subject получили `204`, создали по одной
delivery-записи с ID `00000000-0000-4000-8000-000000009001` и
`00000000-0000-4000-8000-000000009002` и не изменили шесть локальных
пользователей. Эти development secrets нельзя переносить на production.

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
order `60`. На 2026-08-12 production profile/access webhooks еще выключены:
receiver-код подготовлен локально, но миграция, secrets и настройки клиента SSO
еще не развернуты. Credentials хранятся
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
