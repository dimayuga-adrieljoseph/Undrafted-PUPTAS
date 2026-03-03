#!/bin/bash
set -e

# Force disable conflicting MPMs at runtime
a2dismod mpm_event mpm_worker || true
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*

# Ensure only prefork is active
a2enmod mpm_prefork

# Listen on Railway's $PORT
echo "Listen ${PORT:-80}" >> /etc/apache2/ports.conf

# Test config and start
apache2ctl -t
exec apache2-foreground