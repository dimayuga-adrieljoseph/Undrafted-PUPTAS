#!/bin/sh
# start.sh - entrypoint for Laravel container on Railway

# Exit immediately if a command fails
set -e

# Set working directory
cd /var/www

# Ensure storage directories exist
mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache

# Set permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Ensure .env exists (if you didn't copy APP_KEY via env vars)
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Clear and cache Laravel configuration
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Run migrations (forces without prompt)
php artisan migrate --force || true

# Serve the application on Railway’s port
php artisan serve --host=0.0.0.0 --port=${PORT:-8080}