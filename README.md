Система Управления Складом
==========================

Для самых нетерпеливых вот несколько скриншотов приложения: https://yadi.sk/d/YnH35Xn6mpcJP

---
Текущий статус проекта (май 2023)
---------------------------------
На данный момент у меня нет особых планов по развитию данного проекта. Перед этим он 6 лет лежал без изменений. Связано это с тем, что он был написан в 2015 году на фреймворке Yii 2.0, которым я пользовался в тот период на своей основной работе, и хорошо его знал тогда. Потом я сменил работу, сменил фреймворк, и Yii практически забыл. При этом сам Yii мне уже не особо нравится, да и его развитие кажется зашло в тупик. Сейчас я использую Laravel, но от него я тоже не в восторге. У меня есть желание переписать его вообще без фреймворков, чтобы не терять совместимость каждый раз при выходе новых версий, вместо фрейворков я планирую использовать пакеты Composer, которые реализуют PSR-интерфейсы типа роутера, HTTP-message и т.п. У меня есть наработки в этом плане. Но когда дойдут до этого руки я не знаю, т.к. есть ещё один проект, который пока вообще никак не реализован. А пока я обновил этот код до PHP 8.0, версию Yii обновил до последней на текущей момент версии 2.0.47, решив накопившиеся проблемы с зависимостями в `composer.json` и исправил пару существенных недоработок. Bootstrap обновлять не стал, т.к. там слишком много всего поменялось. Дальше скорее всего здесь если и будут, то только незначительные правки. 

---

Что это такое?
--------------
Это довольно простое веб-приложение, написанное на PHP и MySQL, позволяющее автоматизировать складское хранение товаров. Однако, написано оно было прежде всего в личных целях для домашнего использования. Приложение написано с использованием [Yii Framework 2.0](http://www.yiiframework.com/) на базе [Advanced Application Template](https://github.com/yiisoft/yii2-app-advanced).

У меня дома довольно много разных вещей. По своей природе я немного забывчив, так что иногда я помню, что покупал какую-то вещь, но совершенно не представляю где она может лежать. На её поиски у меня может уйти несколько часов и без результата. Кроме того, я иногда покупаю какие-то вещи повторно просто потому что забыл, что уже покупал их или не помню где они лежат. Мне иногда проще купить их снова, чем искать их у себя. В результате моя квартира со временем заполнилась вещами, распиханным по разным шкафам, стеллажам, ящикам. И чем больше вещей накапливалось, тем труднее становилось найти среди них то, что нужно. Разумеется, я пытался систематизировать хранение вещей по категориям, но это не всегда помогает, т.к. вещей слишком много, хранить однотипные вещи в одном месте не всегда возможно, плюс многие вещи относятся сразу к нескольким категориям и непонятно куда их класть.

Чтобы решить все эти проблемы я решил организовать у себя дома ячеистое хранение вещей, создав данную программу. Благодаря ей почти все мои вещи были пронумерованы, им были присвоены уникальные идентификаторы. Некоторые вещи могут быть контейнерами, в этом случае они могут содержать в себе другие вещи рекурсивно. В результате образуется древовидная структура, ветви которой представляют собой пути для поиска нужных предметов. В корне дерева находятся: квартира, машина, дача. Квартира содержит комнаты, коридор, лоджию. Те в свою очередь содержат шкафы, которые делятся на секции, полки, коробочки и так далее. Если мне нужно найти какую-то вещь, я ввожу буквально несколько букв, которые как я предполагаю есть в её названии, описании или в метках, и мгновенно получаю путь к ней. Например: Квартира → Лоджия → Правый стеллаж → Полка 3 → Большая компьютерная коробка → Коробка с компьютерными мелочами → Переходник с DVI на HDMI черный.

Каждая вещь или контейнер содержит название, произвольное описание, несколько меток (тегов), перечисленных через запятую, и несколько фотографий. Метки помогают искать вещи. В метки я добавляю синонимы названия предмета, материал, цвет. Поиск реализован не по целым словам, а по подстроке (SQL LIKE). Это позволяет искать шире, вводя в поиск меньше букв. Хотя и требует немного больше ресурсов.

Загружаемые фотографии уменьшаются до заданных в файле конфигурации размеров для экономии места на диске и повышения скорости работы. По умолчанию фотографии вписываются в квадрат 1024x1024, качество JPEG 90. Фотографии сохраняются в каталоге `photos` в двух-уровневых подпапках с именами от 00 до 99. Например: `photos/84/69/1479.jpg`. Фотографии можно упорядочивать, первая фотография — основная.

Разумеется, не все люди настолько замороченные как я, не у всех хватит времени и терпения забивать в базу данных тысячи предметов, подписывать их, фотографировать, заполнять в их описание истории приобретения, сроки годности и т.п. Так что, если вы интересуетесь системой инвентаризации, то скорее всего у вас какой-то иной предполагаемый сценарий использования. Напишите мне на почту что именно вам нужно, возможно это можно добавить в программу небольшими усилиями.

Требования
----------

Проект будет работать на PHP версии 8.0+ и MySQL/MariaDB 5.5+. Веб-сервер может быть любой, но основной упор делается на nginx и Apache. Возможна также работа и на более старых версиях PHP и MySQL, но я не могу точно сказать какие версии являются минимально допустимыми. Операционная система роли не играет. Для работы приложения требуются следующие расширения PHP:

 * php_gd2
 * php_mbstring
 * php_exif
 * php_pdo_mysql

Расширения `gd2` и `exif` требуются для обработки фотографий после загрузки. Последнее нужно для поворота фотографии, в которой поворот задан посредством EXIF информации (некоторые фотоаппараты сохраняют так кадры с портретной ориентацией). Кроме того, для обработки больших фотографий, сделанных на современные фотоаппараты, может потребоваться много памяти, у меня настройка `memory_limit = 256M`. Лучше всего задать её локально в файле `backend/web/index.php`:

```
ini_set('memory_limit', '256M');
```

Также для добавления сразу нескольких фотографий к предмету за один раз я рекомендую следующие настройки php.ini:

```
upload_max_filesize = 16M
max_file_uploads = 20
post_max_size = 64M
```

Установка
---------

1\. Слейте себе репозитарий через:
```
git clone https://github.com/gugglegum/inventory.git
cd inventory
```
2\. Запустите скрипт `init`, который сгенерирует файлы index.php и локальные конфиги.

3\. Создайте пустую базу данных MySQL и отдельного пользователя, если необходимо.

4\. Отредактируйте файл `common/config/main-local.php`, прописав в нём правильные параметры подключения к созданной вами базе данных.

5\. Установите Composer, если он у вас ещё не установлен. Можно установить его локально в каталог приложения, например, командой:
`php -r "readfile('https://getcomposer.org/installer');" | php`. Другие варианты и опции установки [здесь](https://getcomposer.org/download/).

6\. Запустите `composer.phar install`. Это автоматически установит Yii Framework и прочие зависимости в папку `vendor`.

7\. Примените все миграции командой `yii migrate`. Эта команда создаст в созданной вами базе данных необходимые таблицы и обновит их до актуального состояния.

8\. Настройте веб-сервер (nginx или Apache) таким образом, чтобы корнем www-директории считался подкаталог `backend/web`, а все запросы на несуществующие в этой директории файлы перенаправлялись на `index.php`. Кроме этого все запросы, начинающиеся на `/photo` должны направляться в каталог `photo`. Вот пример рабочего конфига для nginx:

```
    server {
        listen       80;
        server_name  inventory.local;

        # Inventory application path
        set $app_dir C:/Users/Paul/Workspace/gugglegum/projects/inventory;

        root $app_dir/backend/web;

        location / {
            try_files $uri @index-php;
            gzip            on;
            gzip_types      text/css application/javascript;
            expires         2w;
        }

        location /photos {
            alias $app_dir/photos;
        }

        location ~ \.php$ {
            include                 fastcgi_params;
            fastcgi_pass            127.0.0.1:9000;
            fastcgi_index           index.php;
            fastcgi_param           SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        }

        location @index-php {
            rewrite                 ^/(.*)$ /index.php last;
        }
    }
```
По большому счёту в этом примере вам нужно заменить только путь к директории, где находится приложение.

А вот пример конфига для Apache:

```apacheconf
<VirtualHost *:80>
    DocumentRoot "C:/Users/Paul/Workspace/gugglegum/projects/inventory/backend/web"
    ServerName inventory.local
    Alias /thumbnails "C:/Users/Paul/Workspace/gugglegum/projects/inventory/thumbnails"
    Alias /photos "C:/Users/Paul/Workspace/gugglegum/projects/inventory/photos"
    <Directory "C:/Users/Paul/Workspace/gugglegum/projects/inventory/backend/web">
        RewriteEngine on
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule (.*) /index.php [L]
    </Directory>
</VirtualHost>
```

9\. Если вы запускаете приложение на локальной машине, добавьте запись о локальном домене в `/etc/hosts` (в Windows `%systemroot%\system32\drivers\etc\hosts`). Например:

```
127.0.0.1 inventory.local
```

10\. Создайте директорию `photos` в корне приложения и обеспечьте возможность создания в ней поддиректорий пользователем, под которым выполняется PHP-процесс при выполнении веб-приложения.

11\. Если вы всё сделали правильно, то приложение должно открываться в браузере по адресу http://inventory.local/. Первым делом оно попросит вас авторизоваться. Поскольку в системе ещё нет ни одного пользователя, его нужно создать. В веб-приложении есть раздел управления пользователями, но в него нужно сперва как-то попасть. Поэтому первого пользователя нужно создать вручную. Сделать это можно специальной консольной командой, например, так: `yii user/create admin admin@example.com` (вы можете выбрать другое имя пользователя вместо "admin" и указать свой е-мейл). Вам предложат ввести пароль для нового пользователя в консоли или во всплывающем окне. Используйте указанное имя пользователя и пароль для входа в систему.

Советы по использованию
-----------------------

Для быстрого добавления предметов в базу можно воспользоваться функцией импорта. На странице просмотра каждого контейнера внизу есть текстовое поле, куда можно построчно ввести наименования предметов и они все разом добавятся в базу. Можно задать описание предмета, если в следующей за наименованием строке начать строку с "!". Чтобы задать теги строку следует начать с символа "@". Признак контейнера можно задать так: "* container: 1" или "* контейнер: 1". Под текстовым полем имеется галочка "Подтвердить добавление", которая по умолчанию снята. Нажатие кнопки Импорт со снятой галочкой показывает страницу предпросмотра, на которой можно в наглядной форме увидеть что именно будет добавлено.

Обратная связь
--------------
Если у вас возникли какие-либо вопросы или трудности с установкой — смело обращайтесь ко мне по адресу [gugg...@gmail.com](https://mailhide.io/e/U9DWSaDC) (решите каптчу, чтобы увидеть е-мейл адрес полностью).
