#!/bin/bash
set -e

echo "=========================================="
echo "ENTRYPOINT STARTING (SERVICE_ROLE=${SERVICE_ROLE:-web})"
echo "=========================================="

# Create required directories
echo "[1/7] Creating directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache
touch /var/www/html/.env

# Fix permissions
echo "[2/7] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs

# Verify vendor exists
echo "[3/7] Checking vendor..."
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    exit 1
fi

# Validate APP_KEY is set
echo "[4/7] Checking APP_KEY..."
if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY environment variable is not set!"
    exit 1
fi
echo "[4/7] APP_KEY: present"

# Create storage symlink (filesystem-only, no DB/Redis calls)
echo "[5/7] Creating storage symlink..."
mkdir -p storage/app/public/uploads/files
chown -R www-data:www-data storage/app/public
php artisan storage:link --force --no-interaction 2>/dev/null || true
chown -h www-data:www-data public/storage 2>/dev/null || true
echo "[5/7] Storage link created."

# =============================================================================
# Web service: configure Apache and start
# =============================================================================
if [ "${SERVICE_ROLE:-web}" = "web" ]; then
    echo "[6/7] Configuring Apache..."

    # Fix MPM conflict at runtime
    a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true
    rm -f /etc/apache2/mods-enabled/mpm_*.load \
          /etc/apache2/mods-enabled/mpm_*.conf 2>/dev/null || true
    a2enmod mpm_prefork

    # Respect Railway's injected $PORT (fallback: 8080)
    APACHE_PORT="${PORT:-8080}"
    echo "Listen ${APACHE_PORT}" > /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${APACHE_PORT}>/" \
        /etc/apache2/sites-available/000-default.conf

    echo "[7/7] Starting Apache on port ${APACHE_PORT}..."
    echo "=========================================="
    exec apache2-foreground
fi

# =============================================================================
# Worker / Scheduler: execute the passed command
# =============================================================================
if [ "$1" != "" ]; then
    echo "[6/7] Skipping Apache setup (SERVICE_ROLE=${SERVICE_ROLE})"
    echo "[7/7] Executing: $@"
    echo "=========================================="
    exec "$@"
else
    echo "ERROR: No command provided and SERVICE_ROLE is not 'web'."
    echo "Pass a start command or set SERVICE_ROLE=web."
    exit 1
fi
