FROM php:8.4-fpm

# Переключаемся на root для изменения UID/GID
USER root
# Сохраняем исторический UID/GID приложения: существующие photos/thumbnails
# уже принадлежат этому пользователю внутри контейнера.
RUN groupmod -g 33 www-data && usermod -u 33 -g 33 www-data

RUN rm -rf /usr/local/php/php

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libpq-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libfreetype6-dev \
    unzip \
    curl \
    git \
    && docker-php-ext-install curl pdo_mysql \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install gd exif \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN install -d -o www-data -g www-data -m 0775 \
        /var/www/.composer \
        /var/www/.composer/cache \
        /var/www/html/backend/runtime \
        /var/www/html/backend/runtime/cache \
        /var/www/html/backend/runtime/logs \
        /var/www/html/backend/web/assets \
        /var/www/html/console/runtime \
        /var/www/html/console/runtime/cache \
        /var/www/html/frontend/runtime \
        /var/www/html/frontend/runtime/cache \
        /var/www/html/frontend/runtime/logs \
        /var/www/html/frontend/web/assets
RUN git config --global --add safe.directory /var/www/html

# Убедимся, что php-fpm работает от www-data
USER www-data
