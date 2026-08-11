#!/bin/bash
set -e

echo "=========================================="
echo "ENTRYPOINT STARTING"
echo "=========================================="

# Create required directories
echo "[1/10] Creating directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache
touch /var/www/html/.env

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
# (Web service only — skipped for worker/scheduler)
# =============================================================================
if [ "${SERVICE_ROLE:-web}" = "web" ]; then
    echo "[5/10] Checking/fixing Apache MPM..."

    a2dismod mpm_event 2>/dev/null || true
    a2dismod mpm_worker 2>/dev/null || true
    a2dismod mpm_prefork 2>/dev/null || true

    rm -f /etc/apache2/mods-enabled/mpm_*.load 2>/dev/null || true
    rm -f /etc/apache2/mods-enabled/mpm_*.conf 2>/dev/null || true

    a2enmod mpm_prefork

    MPM_COUNT=$(ls -1 /etc/apache2/mods-enabled/mpm_*.load 2>/dev/null | wc -l)
    if [ "$MPM_COUNT" -gt 1 ]; then
        echo "ERROR: Multiple MPMs enabled!"
        ls -la /etc/apache2/mods-enabled/mpm_*.load
        exit 1
    fi
    echo "[5/10] MPM verification: OK ($MPM_COUNT MPM enabled)"

    # -------------------------------------------------------------------
    # Dynamic port: respect Railway's injected $PORT (default 8080)
    # -------------------------------------------------------------------
    APACHE_PORT="${PORT:-8080}"
    echo "[5b/10] Configuring Apache port to: $APACHE_PORT"
    echo "Listen ${APACHE_PORT}" > /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${APACHE_PORT}>/" \
        /etc/apache2/sites-available/000-default.conf
else
    echo "[5/10] Skipping Apache MPM setup (SERVICE_ROLE=${SERVICE_ROLE})"
fi

# Clear Laravel caches
echo "[6/10] Clearing Laravel caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan optimize:clear 2>/dev/null || true

# Validate APP_KEY is set (must be provided via environment variable)
echo "[6b/10] Checking APP_KEY..."
if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY environment variable is not set!"
    echo "Set it via Railway environment variables or your deployment platform."
    exit 1
fi
echo "[6b/10] APP_KEY: present (from environment)"

# Create storage symlink so public disk is accessible
echo "[7/10] Creating storage symlink..."
mkdir -p storage/app/public/uploads/files
chown -R www-data:www-data storage/app/public
php artisan storage:link --force
chown -h www-data:www-data public/storage 2>/dev/null || true
echo "[7/10] Storage link created."

# -------------------------------------------------------------------
# Web-only: test Apache config and start Apache
# Worker/scheduler use exec "$@" path below
# -------------------------------------------------------------------
if [ "${SERVICE_ROLE:-web}" = "web" ]; then
    # Verify routes are registered
    echo "[8/10] Verifying routes..."
    php artisan route:list --path=login 2>/dev/null || echo "Route verification skipped"

    # Test Apache configuration
    echo "[9/10] Testing Apache configuration..."
    apache2ctl configtest
    if [ $? -ne 0 ]; then
        echo "ERROR: Apache configuration test failed!"
        exit 1
    fi

    # List enabled MPM modules
    echo "[10/10] Enabled MPM modules:"
    apache2ctl -M 2>/dev/null | grep mpm || echo "No MPM modules listed"

    # Set proper permissions after cache clear
    chown -R www-data:www-data storage bootstrap/cache

    echo "[START] Starting Apache on port ${PORT:-8080}..."
    echo "=========================================="
    echo "APACHE STARTED SUCCESSFULLY"
    echo "=========================================="
    exec apache2-foreground
fi

# Set proper permissions after cache clear
chown -R www-data:www-data storage bootstrap/cache

# Worker / Scheduler or any custom command
if [ "$1" != "" ]; then
    echo "=========================================="
    echo "EXECUTING: $@"
    echo "=========================================="
    exec "$@"
else
    echo "ERROR: No command provided and SERVICE_ROLE is not 'web'."
    echo "Set SERVICE_ROLE=worker and pass a start command, e.g.:"
    echo "  php artisan queue:work --queue=high,emails,default"
    exit 1
fi
