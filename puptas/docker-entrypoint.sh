#!/bin/bash
set -e

echo "=== Railway Laravel Entry Point (Apache) ==="

# Get the PORT from Railway environment variable (default to 8080)
export PORT=${PORT:-8080}
echo "Using PORT: $PORT"

# Update Apache config to use the correct port
echo "Listen $PORT" > /etc/apache2/ports.conf

# Update Apache VirtualHost to listen on $PORT
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/g" /etc/apache2/sites-available/000-default.conf

# Create required directories
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache

# Fix permissions
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs

# Verify vendor exists
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    ls -la /var/www/html/
    exit 1
fi

echo "Starting Apache on 0.0.0.0:$PORT"

# Start Apache in foreground, binding to all interfaces
exec apache2-foreground
