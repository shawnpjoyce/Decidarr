#!/bin/sh
set -eu

mkdir -p /var/www/html/storage
chown -R www-data:www-data /var/www/html/storage

php-fpm -D
exec nginx -g "daemon off;"
