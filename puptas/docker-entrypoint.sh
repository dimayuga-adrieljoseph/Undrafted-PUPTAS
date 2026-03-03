#!/bin/bash
set -e

echo "=========================================="
echo "ENTRYPOINT STARTING"
echo "=========================================="

# Create required directories
echo "[1/9] Creating directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache

# Fix permissions
echo "[2/9] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs

# Verify vendor exists
echo "[3/9] Checking vendor..."
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    ls -la /var/www/html/
    exit 1
fi

# Check public directory
echo "[4/9] Checking public directory..."
if [ ! -f /var/www/html/public/index.php ]; then
    echo "ERROR: public/index.php not found!"
    exit 1
fi

# Clear Laravel caches
echo "[5/9] Clearing Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Verify MPM configuration
echo "[6/9] Verifying MPM configuration..."
ls -la /etc/apache2/mods-enabled/ | grep mpm

# Test Apache configuration
echo "[7/9] Testing Apache configuration..."
apache2ctl configtest

# Set proper permissions after cache clear
echo "[8/9] Final permission fix..."
chown -R www-data:www-data storage bootstrap/cache

# Start Apache
echo "[9/9] Starting Apache..."
echo "=========================================="
echo "APACHE SHOULD BE RUNNING NOW"
echo "=========================================="

exec apache2-foreground
