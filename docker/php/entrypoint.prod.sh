#!/bin/sh
set -e

mkdir -p /var/www/backend/storage/framework/views \
         /var/www/backend/storage/framework/sessions \
         /var/www/backend/storage/framework/cache \
         /var/www/backend/storage/logs \
         /var/www/backend/storage/app/public \
         /var/www/backend/bootstrap/cache

chown -R www-data:www-data \
    /var/www/backend/storage \
    /var/www/backend/bootstrap/cache

php artisan storage:link --force 2>/dev/null || true

exec php-fpm
