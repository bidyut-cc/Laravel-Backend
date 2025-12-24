FROM php:8.3-fpm-alpine

RUN apk update && apk add --no-cache \
    zip unzip curl git \
    libpng-dev oniguruma-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/src
