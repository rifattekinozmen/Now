FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql zip opcache gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/app/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/app/realpath.ini /usr/local/etc/php/conf.d/realpath.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
