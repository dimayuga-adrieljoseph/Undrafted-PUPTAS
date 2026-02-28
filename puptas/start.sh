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

# Debug: Show database connection info
echo "=== Database Configuration ==="
php artisan tinker --execute="echo 'DB_HOST: ' . env('DB_HOST') . PHP_EOL; echo 'DB_PORT: ' . env('DB_PORT') . PHP_EOL; echo 'DB_DATABASE: ' . env('DB_DATABASE') . PHP_EOL;"

# Test database connection and verify database exists
echo "Testing database connection..."
php artisan tinker --execute="
try {
    \$pdo = DB::connection()->getPdo();
    echo 'Database connection: SUCCESS' . PHP_EOL;
    
    // Try to select from the database
    DB::connection()->select('SELECT 1');
    echo 'Database query: SUCCESS' . PHP_EOL;
} catch (\Exception \$e) {
    echo 'Database connection: FAILED' . PHP_EOL;
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

# Show migration status before running
echo "=== Current Migration Status ==="
php artisan migrate:status

# Run migrations with verbose output
echo "Running database migrations..."
php artisan migrate --force --verbose

if [ $? -eq 0 ]; then
    echo "Migrations completed successfully."
else
    echo "ERROR: Migrations failed!"
    exit 1
fi

# Serve the application on Railway's port
php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
