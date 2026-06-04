FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl libicu-dev libzip-dev nginx \
    && docker-php-ext-install -j$(nproc) pdo_mysql intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/nginx.conf /etc/nginx/sites-available/default
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/memory.ini /usr/local/etc/php/conf.d/memory.ini
COPY docker/php/zz-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-fpm-pool.conf
COPY docker/php/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
