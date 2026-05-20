FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
        autoconf g++ make linux-headers \
        bash git curl libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
        oniguruma-dev icu-dev mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql mbstring zip exif pcntl bcmath gd intl opcache sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN addgroup -g 1000 mesero && adduser -G mesero -u 1000 -D mesero
USER mesero

EXPOSE 9000
CMD ["php-fpm"]
