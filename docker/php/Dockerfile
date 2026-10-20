FROM php:8.3-fpm-alpine

# Instalacja zależności systemowych
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    postgresql-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers

# Instalacja rozszerzeń PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip \
    opcache

# Instalacja Composera
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Kopiowanie konfiguracji PHP
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

WORKDIR /var/www/backend

EXPOSE 9000
CMD ["php-fpm"]