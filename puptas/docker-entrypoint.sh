#!/bin/bash
set -e

# Force ONLY mpm_prefork at runtime (Railway-safe)
a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork

# Handle Railway PORT (defaults to 80)
if [ -n "$PORT" ]; then
    echo "Listen ${PORT}" >> /etc/apache2/ports.conf
    sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf || true
fi

# Test config
apache2ctl -t
exec apache2-foreground
