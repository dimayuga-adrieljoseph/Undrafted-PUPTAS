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

# Copy vendor (needed for things like Ziggy)
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
# Fix Apache MPM Conflict
# -----------------------------
RUN a2dismod mpm_event mpm_worker || true \
    && rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork

RUN a2enmod rewrite headers ssl

# Optional: suppress ServerName warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copy full Laravel app (with vendor)
COPY --from=php-dependencies /app /var/www/html

# Copy built frontend assets
COPY --from=frontend /app/public/build ./public/build

# -----------------------------
# FIX STORAGE PERMISSIONS (critical)
# -----------------------------
RUN mkdir -p \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Cache routes & views (safe)
RUN php artisan route:cache \
    && php artisan view:cache

EXPOSE 80
CMD ["apache2-foreground"]