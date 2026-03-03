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

# Copy vendor from PHP stage (for tools like Ziggy if needed)
COPY --from=php-dependencies /app/vendor ./vendor

RUN npm ci
RUN npm run build

# =============================================================================
# Stage 3: Production Image
# =============================================================================
FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    libzip-dev libonig-dev libxml2-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_mysql zip mbstring exif pcntl bcmath gd

# -----------------------------
# Basic Apache setup (minimal build-time)
# -----------------------------
RUN a2enmod rewrite headers ssl && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Set public folder as document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copy Laravel app (with vendor)
COPY --from=php-dependencies /app /var/www/html

# Copy built frontend assets
COPY --from=frontend /app/public/build ./public/build

# -----------------------------
# Fix Laravel Storage Permissions & Migrations
# -----------------------------
RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Cache Laravel (migrations run in entrypoint to handle DB connection)
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# -----------------------------
# Custom Entrypoint for MPM & Runtime Fixes
# -----------------------------
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8080
CMD ["/usr/local/bin/docker-entrypoint.sh"]
