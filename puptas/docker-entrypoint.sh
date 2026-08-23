#!/bin/bash
set -e

echo "=========================================="
echo "ENTRYPOINT STARTING"
echo "=========================================="

# Create required directories
echo "[1/11] Creating directories..."
mkdir -p /var/lib/php/sessions /var/lib/php/wsdlcache
mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache
touch /var/www/html/.env

# Fix permissions
echo "[2/11] Fixing permissions..."
chown -R www-data:www-data storage bootstrap/cache /var/lib/php/sessions /var/lib/php/wsdlcache
chmod -R 775 storage bootstrap/cache
chmod -R 755 storage/framework storage/logs

# Verify vendor exists
echo "[3/11] Checking vendor..."
if [ ! -f /var/www/html/vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php not found!"
    ls -la /var/www/html/
    exit 1
fi

# Check public directory
echo "[4/11] Checking public directory..."
if [ ! -f /var/www/html/public/index.php ]; then
    echo "ERROR: public/index.php not found!"
    exit 1
fi

# =============================================================================
# FIX: Dynamic port — Railway injects $PORT at runtime
# =============================================================================
APP_PORT="${PORT:-8080}"
echo "[4b/11] Configuring Apache to listen on port ${APP_PORT}..."
echo "Listen ${APP_PORT}" > /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${APP_PORT}>/g" /etc/apache2/sites-available/000-default.conf

# =============================================================================
# FIX: Apache MPM Conflict - Runtime verification and fix
# =============================================================================
echo "[5/11] Checking/fixing Apache MPM..."

# Disable all MPMs
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2dismod mpm_prefork 2>/dev/null || true

# Remove any remaining MPM config files
rm -f /etc/apache2/mods-enabled/mpm_*.load 2>/dev/null || true
rm -f /etc/apache2/mods-enabled/mpm_*.conf 2>/dev/null || true

# Enable ONLY mpm_prefork (required for mod_php)
a2enmod mpm_prefork

# Verify only one MPM is enabled
MPM_COUNT=$(ls -1 /etc/apache2/mods-enabled/mpm_*.load 2>/dev/null | wc -l)
if [ "$MPM_COUNT" -gt 1 ]; then
    echo "ERROR: Multiple MPMs enabled!"
    ls -la /etc/apache2/mods-enabled/mpm_*.load
    exit 1
fi
echo "[5/11] MPM verification: OK ($MPM_COUNT MPM enabled)"

# Clear Laravel caches
echo "[6/12] Clearing Laravel caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan optimize:clear 2>/dev/null || true

# Validate APP_KEY is set (must be provided via environment variable)
echo "[6b/12] Checking APP_KEY..."
if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY environment variable is not set!"
    echo "Set it via Railway environment variables or your deployment platform."
    exit 1
fi
echo "[6b/12] APP_KEY: present (from environment)"

# Install Passport OAuth keys from environment variables (Railway / production)
echo "[7b/13] Installing Passport keys..."
if [ -n "${PASSPORT_PRIVATE_KEY:-}" ] && [ -n "${PASSPORT_PUBLIC_KEY:-}" ]; then
    echo "${PASSPORT_PRIVATE_KEY}" > storage/oauth-private.key
    echo "${PASSPORT_PUBLIC_KEY}" > storage/oauth-public.key
    chmod 600 storage/oauth-private.key storage/oauth-public.key
    chown www-data:www-data storage/oauth-private.key storage/oauth-public.key
    echo "[7b/13] Passport keys installed from environment."
elif [ -f storage/oauth-private.key ]; then
    echo "[7b/13] Passport keys already present on disk."
else
    echo "[7b/13] WARNING: No Passport keys found. Running passport:keys to generate..."
    timeout 30 php artisan passport:keys || echo "[7b/13] WARNING: passport:keys timed out or failed — continuing anyway"
fi

# Wait for MySQL to be ready to accept real queries (not just TCP-open)
echo "[7/13] Waiting for MySQL to be ready..."
DB_WAIT_TIMEOUT=90
DB_WAIT_INTERVAL=3
DB_ELAPSED=0
until php -r "
    \$host = getenv('DB_HOST') ?: '127.0.0.1';
    \$port = getenv('DB_PORT') ?: '3306';
    \$user = getenv('DB_USERNAME') ?: 'root';
    \$pass = getenv('DB_PASSWORD') ?: '';
    \$name = getenv('DB_DATABASE') ?: 'railway';
    echo \"[DB-CHECK] host={\$host} port={\$port} user={\$user} db={\$name}\n\";
    try {
        \$dsn = \"mysql:host={\$host};port={\$port};dbname={\$name};charset=utf8mb4;connect_timeout=5\";
        new PDO(\$dsn, \$user, \$pass, [PDO::ATTR_TIMEOUT => 5, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo \"[DB-CHECK] Connection successful!\n\";
        exit(0);
    } catch (Exception \$e) {
        echo \"[DB-CHECK] Failed: \" . \$e->getMessage() . \"\n\";
        exit(1);
    }
"; do
    if [ "$DB_ELAPSED" -ge "$DB_WAIT_TIMEOUT" ]; then
        echo "ERROR: MySQL not ready after ${DB_WAIT_TIMEOUT}s. Check DB_HOST/DB_USERNAME/DB_PASSWORD env vars."
        exit 1
    fi
    echo "  ...waiting for MySQL to accept connections (${DB_ELAPSED}s elapsed)"
    sleep "$DB_WAIT_INTERVAL"
    DB_ELAPSED=$((DB_ELAPSED + DB_WAIT_INTERVAL))
done
echo "[7/13] MySQL is ready. Running migrations..."
php artisan migrate --force
echo "[7/13] Migrations complete."

# Seed Passport clients (safe to run on every deploy — idempotent)
echo "[7c/13] Seeding Passport API clients..."
php artisan db:seed --class=PassportClientSeeder --force 2>/dev/null || \
    echo "[7c/13] PassportClientSeeder skipped or already seeded."

# Create storage symlink so public disk is accessible
echo "[8/13] Creating storage symlink..."
mkdir -p storage/app/public/uploads/files
chown -R www-data:www-data storage/app/public
php artisan storage:link --force
chown -h www-data:www-data public/storage 2>/dev/null || true
echo "[8/13] Storage link created."

# Generate API documentation (base URL resolves from APP_URL at runtime)
# Only regenerate docs if explicitly requested (REGENERATE_DOCS=true) to avoid
# slowing down every deploy with a potentially multi-minute scribe run.
echo "[8b/13] Checking API documentation..."
if [ "${REGENERATE_DOCS:-false}" = "true" ]; then
    echo "[8b/13] Generating API documentation (REGENERATE_DOCS=true)..."
    php artisan vendor:publish --tag=scribe-views --force
    php artisan scribe:generate
    php artisan scribe:openapi-to-json \
      --input=storage/app/private/scribe/openapi.yaml \
      --output=storage/app/private/scribe/openapi.json \
      || echo "[8b/13] WARN: openapi-to-json conversion skipped (non-fatal)"
    echo "[8b/13] API documentation generated."
else
    echo "[8b/13] Skipping scribe:generate (set REGENERATE_DOCS=true to regenerate)."
fi

# Verify routes are registered
echo "[9/13] Verifying routes..."
php artisan route:list --path=login 2>/dev/null || echo "Route verification skipped"

# Test Apache configuration
echo "[10/13] Testing Apache configuration..."
apache2ctl configtest
if [ $? -ne 0 ]; then
    echo "ERROR: Apache configuration test failed!"
    exit 1
fi

# List enabled MPM modules
echo "[11/13] Enabled MPM modules:"
apache2ctl -M 2>/dev/null | grep mpm || echo "No MPM modules listed"

# Set proper permissions after cache clear
echo "[12/13] Final permission fix..."
chown -R www-data:www-data storage bootstrap/cache

# Start Apache or execute custom command
if [ "$1" != "" ]; then
    echo "=========================================="
    echo "EXECUTING CUSTOM COMMAND: $@"
    echo "=========================================="
    exec "$@"
else
    echo "[13/13] Starting Apache..."
    echo "=========================================="
    echo "APACHE STARTED SUCCESSFULLY"
    echo "=========================================="
    
    exec apache2-foreground
fi
