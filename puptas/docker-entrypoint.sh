#!/bin/bash
set -e

echo "=========================================="
echo "ENTRYPOINT STARTING"
echo "=========================================="

# Create required directories
echo "[1/10] Creating directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache

# Fix permissions
echo "[2/10] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs

# Verify vendor exists
echo "[3/10] Checking vendor..."
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    ls -la /var/www/html/
    exit 1
fi

# Check public directory
echo "[4/10] Checking public directory..."
if [ ! -f /var/www/html/public/index.php ]; then
    echo "ERROR: public/index.php not found!"
    exit 1
fi

# =============================================================================
# FIX: Apache MPM Conflict - Runtime verification and fix
# =============================================================================
echo "[5/10] Checking/fixing Apache MPM..."

# Disable all MPMs
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true

# Remove any remaining MPM config files
rm -f /etc/apache2/mods-enabled/mpm_*.load 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_*.conf 2>/dev/null || true

# Enable ONLY mpm_prefork (required for mod_php)
a2enmod mpm_prefork

# Verify only one MPM is enabled
MPM_COUNT=$(ls -1 /etc/apache2/mods-enabled/mpm_*.load 2>/dev/null | wc -l)
if [ "$MPM_COUNT" -gt 1 ]; then
    echo "ERROR: Multiple MPMs enabled!"
    ls -la /etc/apache2/mods-enabled/mpm_*.load
    exit 1
fi
echo "[5/10] MPM verification: OK ($MPM_COUNT MPM enabled)"

# Clear Laravel caches
echo "[6/10] Clearing Laravel caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

# Test Apache configuration
echo "[7/10] Testing Apache configuration..."
apache2ctl configtest
if [ $? -ne 0 ]; then
    echo "ERROR: Apache configuration test failed!"
    exit 1
fi

# List enabled MPM modules
echo "[8/10] Enabled MPM modules:"
apache2ctl -M 2>/dev/null | grep mpm || echo "No MPM modules listed"

# Set proper permissions after cache clear
echo "[9/10] Final permission fix..."
chown -R www-data:www-data storage bootstrap/cache

# Start Apache
echo "[10/10] Starting Apache..."
echo "=========================================="
echo "APACHE STARTED SUCCESSFULLY"
echo "=========================================="

exec apache2-foreground
