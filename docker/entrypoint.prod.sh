#!/bin/sh
set -e

php artisan migrate --force
php artisan optimize
exec php-fpm
