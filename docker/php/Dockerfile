FROM php:8.4-fpm

# Переключаемся на root для изменения UID/GID
USER root
# Синхронизация UID/GID для www-data с хостом
RUN groupmod -g 33 www-data && usermod -u 33 -g 33 www-data

RUN rm -rf /usr/local/php/php

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libfreetype6-dev \
    unzip \
    curl \
    git \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install gd exif \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN mkdir -p /var/www/.composer/cache && chown -R www-data:www-data /var/www/.composer && chmod -R 775 /var/www/.composer
RUN chown -R www-data:www-data /var/www/html && chmod -R 775 /var/www/html
RUN git config --global --add safe.directory /var/www/html

# Убедимся, что php-fpm работает от www-data
USER www-data
