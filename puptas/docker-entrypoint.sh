#!/bin/bash
set -e

echo "=========================================="
echo "ENTRYPOINT STARTING (SERVICE_ROLE=${SERVICE_ROLE:-web})"
echo "=========================================="

# Validate APP_KEY is set
if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY environment variable is not set!"
    exit 1
fi

# Ensure writable runtime directories exist and have correct ownership
# (These may be reset between deploys on ephemeral filesystems)
mkdir -p storage/framework/{sessions,views,cache,maintenance} \
         storage/logs \
         storage/app/public/uploads/files \
         bootstrap/cache \
         /var/lib/php/sessions \
         /var/lib/php/wsdlcache

chown -R www-data:www-data storage bootstrap/cache \
                           /var/lib/php/sessions \
                           /var/lib/php/wsdlcache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs

# =============================================================================
# Web service: configure Apache port and start immediately
# No artisan commands — nothing that touches DB or Redis before Apache is up
# =============================================================================
if [ "${SERVICE_ROLE:-web}" = "web" ]; then
    # Fix MPM conflict at runtime (mods-enabled state can be reset by Railway's volume mount)
    a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true
    rm -f /etc/apache2/mods-enabled/mpm_*.load \
          /etc/apache2/mods-enabled/mpm_*.conf 2>/dev/null || true
    a2enmod mpm_prefork 2>/dev/null

    APACHE_PORT="${PORT:-8080}"
    echo "Listen ${APACHE_PORT}" > /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${APACHE_PORT}>/" \
        /etc/apache2/sites-available/000-default.conf

    echo "Starting Apache on port ${APACHE_PORT}..."
    exec apache2-foreground
fi

# =============================================================================
# Worker / Scheduler
# =============================================================================
if [ "$1" != "" ]; then
    echo "Starting: $@"
    exec "$@"
else
    echo "ERROR: No command provided and SERVICE_ROLE is not 'web'."
    exit 1
fi
