#!/bin/sh
# start.sh - entrypoint for Laravel container on Railway

# Set working directory
cd /var/www

# Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Generate APP_KEY if not set (for fresh installs)
if [ -z "$APP_KEY" ]; then
    if [ ! -f .env ]; then
        cp .env.example .env
    fi
    php artisan key:generate --force
fi

# Clear Laravel configuration cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Run migrations
php artisan migrate --force

# Start PHP-FPM in the background
php-fpm &

# Start Nginx in the foreground
nginx -g 'daemon off;'
