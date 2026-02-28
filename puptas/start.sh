#!/bin/sh
set -e

cd /var/www

# Ensure storage directories exist and permissions
mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Run migrations (skip if fails)
php artisan migrate --force || echo "Migrations failed, check logs"

# Serve Laravel on Railway port
php artisan serve --host=0.0.0.0 --port=${PORT:-8080}