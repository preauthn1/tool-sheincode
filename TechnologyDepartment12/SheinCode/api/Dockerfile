FROM public.ecr.aws/docker/library/php:8.2-cli-alpine

RUN apk add --no-cache git unzip libzip-dev oniguruma-dev icu-dev linux-headers autoconf g++ make \
    && docker-php-ext-install pdo_mysql mysqli mbstring zip intl bcmath pcntl

COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader 2>/dev/null || true
