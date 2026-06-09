FROM php:8.5-cli-alpine

# System deps for PHP extensions.
RUN apk add --no-cache \
    linux-headers \
    autoconf g++ make

# Install mysqli (not built-in on Alpine).
RUN docker-php-ext-install mysqli

# XDebug for coverage.
RUN pecl install xdebug && docker-php-ext-enable xdebug

# Composer.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN mkdir -p /app && chown www-data:www-data /app
WORKDIR /app
