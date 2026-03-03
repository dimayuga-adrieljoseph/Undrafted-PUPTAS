#!/bin/bash
set -e

echo "=== Railway Laravel Entrypoint (Nginx + PHP-FPM via Unix Socket) ==="

# =============================================================================
# Fix 1: PHP-FPM Unix Socket Configuration
# =============================================================================
echo "Configuring PHP-FPM to use Unix socket..."

# Create PHP-FPM pool configuration with Unix socket
cat > /usr/local/etc/php-fpm.d/www.conf << 'EOF'
[www]
user = www-data
group = www-data

; Use Unix socket instead of TCP - more reliable in containers
listen = /var/run/php-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

; Process management
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

; Security - prevent executable uploads
security.limit_extensions = .php

; Logging
php_admin_value[error_log] = /var/log/php-fpm/error.log
php_admin_flag[log_errors] = on

; Request characteristics
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/sessions
php_value[soap.wsdl_cache_dir] = /var/lib/php/wsdlcache
EOF

echo "PHP-FPM configuration updated (Unix socket)"

# =============================================================================
# Fix 2: Create required directories
# =============================================================================
echo "Creating required directories..."
mkdir -p /var/run
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p /var/log/php-fpm
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache

# =============================================================================
# Fix 3: Fix Laravel storage permissions
# =============================================================================
echo "Fixing Laravel storage permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache /var/log/php-fpm /var/run
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs
chmod 755 /var/lib/php/sessions /var/lib/php/wsdlcache
chmod 755 /var/run
echo "Permissions fixed"

# =============================================================================
# Fix 4: Ensure vendor/autoload.php exists
# =============================================================================
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    ls -la /var/www/html/
    exit 1
fi
echo "Vendor autoload.php found"

# =============================================================================
# Fix 5: Test PHP-FPM configuration
# =============================================================================
echo "Testing PHP-FPM configuration..."
/usr/local/sbin/php-fpm --test || { echo "PHP-FPM test failed!"; exit 1; }

# =============================================================================
# Fix 6: Test Nginx configuration
# =============================================================================
echo "Testing Nginx configuration..."
nginx -t || { echo "Nginx test failed!"; exit 1; }

# =============================================================================
# Fix 7: Start PHP-FPM first (wait for socket)
# =============================================================================
echo "Starting PHP-FPM..."
/usr/local/sbin/php-fpm --nodaemonize --fpm-config /usr/local/etc/php-fpm.conf &
FPID=$!
sleep 2

# Verify socket was created
if [ ! -S /var/run/php-fpm.sock ]; then
    echo "ERROR: PHP-FPM socket not created!"
    kill $FPID 2>/dev/null || true
    exit 1
fi
echo "PHP-FPM socket created: /var/run/php-fpm.sock"

# =============================================================================
# Start Nginx
# =============================================================================
echo "Starting Nginx..."
nginx

echo "=== All services started ==="
echo "PHP-FPM: Unix socket /var/run/php-fpm.sock"
echo "Nginx: Port 80"

# Keep container running
wait
