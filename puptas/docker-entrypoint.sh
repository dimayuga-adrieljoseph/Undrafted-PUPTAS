#!/bin/bash
set -e

echo "=========================================="
echo "ENTRYPOINT STARTING"
echo "=========================================="

# Create required directories
echo "[1/6] Creating directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache

# Fix permissions
echo "[2/6] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs

# Verify vendor exists
echo "[3/6] Checking vendor..."
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    ls -la /var/www/html/
    exit 1
fi

# Check public directory
echo "[4/6] Checking public directory..."
if [ ! -f /var/www/html/public/index.php ]; then
    echo "ERROR: public/index.php not found!"
    exit 1
fi

# Check Apache modules
echo "[5/6] Checking Apache config..."
apache2ctl -t 2>&1 || true

# Start Apache
echo "[6/6] Starting Apache..."
echo "=========================================="
echo "APACHE SHOULD BE RUNNING NOW"
echo "=========================================="

# Use exec to properly replace the process
exec apache2-foreground
