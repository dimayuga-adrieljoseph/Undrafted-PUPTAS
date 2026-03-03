#!/bin/bash
set -e

echo "=========================================="
echo "ENTRYPOINT STARTING"
echo "=========================================="

# Create required directories
echo "[1/8] Creating directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache

# Fix permissions
echo "[2/8] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs

# Verify vendor exists
echo "[3/8] Checking vendor..."
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    ls -la /var/www/html/
    exit 1
fi

# Check public directory
echo "[4/8] Checking public directory..."
if [ ! -f /var/www/html/public/index.php ]; then
    echo "ERROR: public/index.php not found!"
    exit 1
fi

# Fix MPM conflict (ensure only one MPM is loaded)
echo "[5/8] Fixing MPM configuration..."
a2dismod mpm_event || true
a2dismod mpm_worker || true
a2dismod mpm_prefork || true
a2enmod mpm_prefork

# Verify MPM configuration
echo "[6/8] Verifying MPM configuration..."
ls -la /etc/apache2/mods-enabled/ | grep mpm || true

# Test Apache configuration
echo "[7/8] Testing Apache configuration..."
apache2ctl configtest

# Start Apache
echo "[8/8] Starting Apache..."
echo "=========================================="
echo "APACHE SHOULD BE RUNNING NOW"
echo "=========================================="

exec apache2-foreground