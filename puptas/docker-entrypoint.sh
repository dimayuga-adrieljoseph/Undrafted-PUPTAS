#!/bin/bash
set -e

# Force ONLY mpm_prefork at runtime (Railway-safe)
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork

# Fix Apache PORT binding (clean slate)
PORTS_CONF="/etc/apache2/ports.conf"
if [ -n "$PORT" ]; then
    sed -i '/^Listen /d' "$PORTS_CONF"
    echo "Listen 0.0.0.0:${PORT}" >> "$PORTS_CONF"
    echo "Apache listening on PORT ${PORT}"
else
    echo "No PORT env var, using default 80"
fi

# -----------------------------
# Fix 2: Runtime Laravel Storage Permissions
# -----------------------------
echo "Fixing Laravel storage permissions..."
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs
echo "Storage permissions fixed"

# -----------------------------
# Wait for MySQL (Railway fix)
# -----------------------------
echo "Waiting for MySQL database..."
for i in {1..30}; do
    if php artisan db:show --no-interaction 2>/dev/null; then
        echo "✅ Database ready!"
        break
    fi
    echo "⏳ Waiting for DB... ($i/30)"
    sleep 2
done

# Laravel migrations (only if DB works)
if php artisan db:show --no-interaction >/dev/null 2>&1; then
    echo "🚀 Running migrations..."
    php artisan migrate --force --no-interaction
    php artisan config:cache
    echo "✅ Migrations complete"
else
    echo "⚠️  No working DB connection - skipping migrations (app will still start)"
fi

# Test config & start Apache
echo "Testing Apache configuration..."
apache2ctl -t
echo "🚀 Starting Apache..."
exec apache2-foreground

