#!/bin/bash
set -e

# Force ONLY mpm_prefork at runtime (Railway-safe)
a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork

# Handle Railway PORT (clean slate first)
PORTS_CONF="/etc/apache2/ports.conf"
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    # Clean existing Listen lines to prevent duplicates
    sed -i '/^Listen /d' "$PORTS_CONF"
    # Add single Listen directive
    echo "Listen ${PORT}" >> "$PORTS_CONF"
    echo "PORT set to ${PORT}"
fi

# Run Laravel migrations if DB vars present
if [ -n "$DB_CONNECTION" ] || [ -n "$DATABASE_URL" ]; then
    php artisan migrate --force --no-interaction || echo "Migrations skipped"
fi

# Test config & start
apache2ctl -t
exec apache2-foreground
