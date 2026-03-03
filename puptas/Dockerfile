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
# Stage 2: Frontend Build
# =============================================================================
FROM node:20-alpine AS frontend

WORKDIR /app

COPY . .
COPY --from=php-dependencies /app/vendor ./vendor

RUN npm ci && npm run build

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

# Enable Apache mod_rewrite
RUN a2enmod rewrite headers

# Fix MPM conflict - disable event and worker, enable prefork only
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true
RUN a2enmod mpm_prefork 2>/dev/null || true

# Clean up any conflicting MPM configs
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
           /etc/apache2/mods-enabled/mpm_event.conf \
           /etc/apache2/mods-enabled/mpm_worker.load \
           /etc/apache2/mods-enabled/mpm_worker.conf 2>/dev/null || true

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

# Copy built frontend assets
COPY --from=frontend /app/public/build ./public/build

# Fix permissions
RUN mkdir -p storage/framework/{sessions,views,cache,maintenance} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chmod -R 755 storage/framework storage/logs

# Copy entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
