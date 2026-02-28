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

# Generate APP_KEY if not set (for fresh installs)
if [ -z "$APP_KEY" ]; then
    if [ ! -f .env ]; then
        cp .env.example .env
    fi
    php artisan key:generate --force
fi

# Clear and cache Laravel configuration
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Run migrations with proper error handling
echo "Running database migrations..."
php artisan migrate --force

if [ $? -eq 0 ]; then
    echo "Migrations completed successfully."
else
    echo "ERROR: Migrations failed!"
    exit 1
fi

# Serve the application on Railway's port
php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
