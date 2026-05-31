#!/bin/sh
set -e

if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist
fi

mkdir -p var/cache var/log
chown -R www-data:www-data var/cache var/log 2>/dev/null || true
chmod -R 775 var/cache var/log 2>/dev/null || true

# Console must run as www-data so PHP-FPM can write the cache volume afterward.
run_as_www_data() {
    su -s /bin/sh www-data -c "$*"
}

run_as_www_data 'php bin/console doctrine:migrations:migrate --no-interaction' 2>/dev/null || true
run_as_www_data 'php bin/console cache:warmup --no-debug' 2>/dev/null || true
chown -R www-data:www-data var/cache var/log 2>/dev/null || true
chmod -R 775 var/cache var/log 2>/dev/null || true

php-fpm -D
exec nginx -g 'daemon off;'
