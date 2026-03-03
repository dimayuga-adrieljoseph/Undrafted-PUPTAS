#!/bin/bash
set -e

echo "=========================================="
echo "ENTRYPOINT STARTING"
echo "=========================================="

# Create required directories
echo "[1/7] Creating directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache

# Fix permissions
echo "[2/7] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs

# Verify vendor exists
echo "[3/7] Checking vendor..."
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    ls -la /var/www/html/
    exit 1
fi

# Check public directory
echo "[4/7] Checking public directory..."
if [ ! -f /var/www/html/public/index.php ]; then
    echo "ERROR: public/index.php not found!"
    exit 1
fi

# Verify MPM configuration
echo "[5/7] Verifying MPM configuration..."
ls -la /etc/apache2/mods-enabled/ | grep mpm

# Test Apache configuration
echo "[6/7] Testing Apache configuration..."
apache2ctl configtest

# Start Apache
echo "[7/7] Starting Apache..."
echo "=========================================="
echo "APACHE SHOULD BE RUNNING NOW"
echo "=========================================="

exec apache2-foreground
