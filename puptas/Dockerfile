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

# Copy entire Laravel project (artisan must exist)
COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader


# =============================================================================
# Stage 2: Frontend Build
# =============================================================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy full project
COPY . .

# Copy vendor from php stage (needed for Ziggy)
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
# Fix MPM conflict
# -----------------------------
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
           /etc/apache2/mods-enabled/mpm_*.conf \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load \
    && ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# Enable other needed modules
RUN a2enmod rewrite headers ssl

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

# Copy Laravel app INCLUDING vendor
COPY --from=php-dependencies /app /var/www/html

# Copy built frontend assets
COPY --from=frontend /app/public/build ./public/build

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
RUN chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Optimize Laravel
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Verify only one MPM is loaded
RUN apachectl -M | grep mpm_prefork

EXPOSE 80
CMD ["apache2-foreground"]