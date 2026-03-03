#!/bin/bash
set -e

echo "=== Railway Laravel Entrypoint (Nginx + PHP-FPM) ==="

# =============================================================================
# Fix 1: PHP-FPM TCP Configuration (ensure it listens on 127.0.0.1:9000)
# =============================================================================
echo "Configuring PHP-FPM to listen on TCP 127.0.0.1:9000..."

# Backup existing www.conf if it exists
if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then
    cp /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/www.conf.bak
fi

# Create PHP-FPM pool configuration with explicit TCP binding
cat > /usr/local/etc/php-fpm.d/www.conf << 'EOF'
[www]
user = www-data
group = www-data

; Listen on TCP (127.0.0.1:9000) - CRITICAL for Nginx communication
listen = 127.0.0.1:9000
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
security.limit_extensions = .php .php3 .php4 .php5 .php7 .php8

; Logging
php_admin_value[error_log] = /var/log/php-fpm/error.log
php_admin_flag[log_errors] = on

; Request characteristics
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/sessions
php_value[soap.wsdl_cache_dir] = /var/lib/php/wsdlcache
EOF

echo "PHP-FPM configuration updated"

# =============================================================================
# Fix 2: Create required directories
# =============================================================================
echo "Creating required directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p /var/log/php-fpm
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs

# =============================================================================
# Fix 3: Fix Laravel storage permissions
# =============================================================================
echo "Fixing Laravel storage permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache /var/log/php-fpm
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs
chmod 755 /var/lib/php/sessions /var/lib/php/wsdlcache
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
# Start services via Supervisor
# =============================================================================
echo "Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
