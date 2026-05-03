#!/usr/bin/env sh
set -e

cd /var/www/app

if [ ! -f vendor/autoload_runtime.php ]; then
    composer install --no-interaction --prefer-dist
fi

mkdir -p var/cache var/log
chown -R www-data:www-data var vendor

exec "$@"
