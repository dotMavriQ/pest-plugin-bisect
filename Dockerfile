FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip libzip-dev libxml2-dev libonig-dev \
    && docker-php-ext-install -j"$(nproc)" \
      dom xml mbstring pcntl posix sockets zip \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /app
