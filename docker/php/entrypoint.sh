#!/bin/sh
set -eu
# The key is generated once and persists independently of image rebuilds.
if [ ! -f /var/www/runtime/.env ]; then
    cp /var/www/html/.env.example /var/www/runtime/.env
    php artisan key:generate --force --no-interaction
fi
chown -R www-data:www-data /var/www/runtime /var/www/html/storage /var/www/html/bootstrap/cache
exec "$@"
