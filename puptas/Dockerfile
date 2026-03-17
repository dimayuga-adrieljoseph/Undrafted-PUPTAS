# =============================================================================
# Stage 1: PHP Dependencies
# =============================================================================
FROM php:8.4-cli AS php-dependencies

RUN apt-get update && apt-get install -y \
    git curl \
    libzip-dev libonig-dev libxml2-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_mysql zip mbstring exif pcntl bcmath gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# =============================================================================
# Stage 2: Frontend Build (Vite)
# =============================================================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy package files
COPY package.json package-lock.json* ./

# Install dependencies
RUN npm ci

# Copy full source
COPY . .

# Copy vendor
COPY --from=php-dependencies /app/vendor ./vendor

# Remove any stale committed build assets before building fresh
RUN rm -rf public/build

# Build Vite assets
RUN npm run build

# Verify build output
RUN echo "=== Build manifest check ===" && head -5 public/build/manifest.json && ls public/build/assets/ | head -10

# =============================================================================
# Stage 3: Production Image (Apache)
# =============================================================================
FROM php:8.4-apache

# Install additional PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev libonig-dev libxml2-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_mysql zip mbstring exif pcntl bcmath gd

# Fix Apache MPM Conflict
RUN echo "=== Fixing Apache MPM Conflict ===" \
    && a2dismod mpm_event \
    && a2dismod mpm_worker \
    && a2dismod mpm_prefork \
    && rm -f /etc/apache2/mods-enabled/mpm_*.load \
    && rm -f /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && a2enmod headers \
    && echo "=== MPM Fix Complete ==="

# Set document root to Laravel public folder
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e "s|/var/www/html|${APACHE_DOCUMENT_ROOT}|g" \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf \
    && sed -ri -e "s|AllowOverride None|AllowOverride All|g" \
    /etc/apache2/apache2.conf

# Configure Apache to listen on port 8080
RUN echo "Listen 8080" > /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copy Laravel app
COPY --from=php-dependencies /app /var/www/html

# Copy built frontend assets INCLUDING manifest.json
COPY --from=frontend /app/public/build /var/www/html/public/build

# Verify build exists
RUN ls -la /var/www/html/public/build/ && \
    ls -la /var/www/html/public/build/manifest.json

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Fix permissions
RUN mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod -R 755 storage/framework storage/logs

# Note: APP_KEY is provided via Railway environment variables
# No need to generate at build time

RUN mkdir -p storage/app/templates && \
    cp docs/SAR-FORM_TEMPLATE-2.pdf storage/app/templates/

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
