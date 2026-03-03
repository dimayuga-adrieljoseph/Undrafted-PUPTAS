#!/bin/bash
set -e

echo "=========================================="
echo "Railway Laravel Entrypoint"
echo "=========================================="

# =============================================================================
# Step 1: Configure PHP-FPM with TCP (127.0.0.1:9000)
# =============================================================================
echo "[1/7] Configuring PHP-FPM..."

cat > /usr/local/etc/php-fpm.d/www.conf << 'EOF'
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9000
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
security.limit_extensions = .php
php_admin_value[error_log] = /var/log/php-fpm/error.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/sessions
EOF

echo "PHP-FPM configured: listen=127.0.0.1:9000"

# =============================================================================
# Step 2: Create required directories
# =============================================================================
echo "[2/7] Creating directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache /var/log/php-fpm /var/run
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache
echo "Directories created"

# =============================================================================
# Step 3: Fix permissions
# =============================================================================
echo "[3/7] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache /var/log/php-fpm /var/run
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs
chmod 755 /var/lib/php/sessions /var/lib/php/wsdlcache /var/run
echo "Permissions fixed"

# =============================================================================
# Step 4: Verify vendor exists
# =============================================================================
echo "[4/7] Checking vendor..."
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    ls -la /var/www/html/
    exit 1
fi
echo "Vendor OK"

# =============================================================================
# Step 5: Test configs
# =============================================================================
echo "[5/7] Testing configurations..."
/usr/local/sbin/php-fpm --test || { echo "PHP-FPM config FAILED!"; exit 1; }
nginx -t || { echo "Nginx config FAILED!"; exit 1; }
echo "Config tests passed"

# =============================================================================
# Step 6: Start PHP-FPM
# =============================================================================
echo "[6/7] Starting PHP-FPM..."
/usr/local/sbin/php-fpm --nodaemonize --fpm-config /usr/local/etc/php-fpm.conf &
FPID=$!

# Wait and verify PHP-FPM started
sleep 3

if ! ps -p $FPID > /dev/null 2>&1; then
    echo "ERROR: PHP-FPM failed to start!"
    cat /var/log/php-fpm/error.log 2>/dev/null || true
    exit 1
fi

# Verify port 9000 is listening
if ! netstat -tlnp 2>/dev/null | grep -q ':9000' && ! ss -tlnp 2>/dev/null | grep -q ':9000'; then
    echo "WARNING: Port 9000 not listening, checking process..."
    ps aux | grep php-fpm
fi

echo "PHP-FPM started (PID: $FPID)"
netstat -tlnp 2>/dev/null | grep 9000 || ss -tlnp 2>/dev/null | grep 9000 || echo "Port check done"

# =============================================================================
# Step 7: Start Nginx
# =============================================================================
echo "[7/7] Starting Nginx..."
nginx

# Verify Nginx started
sleep 1
if ! pgrep -x nginx > /dev/null; then
    echo "ERROR: Nginx failed to start!"
    cat /var/log/nginx/error.log 2>/dev/null || true
    exit 1
fi
echo "Nginx started"

echo "=========================================="
echo "All services started successfully!"
echo "=========================================="
echo "PHP-FPM: 127.0.0.1:9000 (TCP)"
echo "Nginx: Port 80"
echo "=========================================="

# Keep container running
tail -f /dev/null
