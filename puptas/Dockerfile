# =============================================================================
# Stage 1: Install PHP Dependencies (needed for frontend build)
# =============================================================================
FROM php:8.4-cli AS php-dependencies

# Install system dependencies and GD extension
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions including GD
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy dependency files
COPY puptas/composer.json puptas/composer.lock* ./

# Run composer update to get compatible packages for PHP 8.2
# RUN composer update --no-dev --no-interaction --prefer-dist

# Copy full application
COPY puptas/ .


# =============================================================================
# Stage 2: Build Frontend Assets (needs vendor directory for Ziggy)
# =============================================================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy package files
COPY puptas/package.json puptas/package-lock.json* ./

# Install npm dependencies
RUN npm ci

# Copy vendor directory from PHP stage (needed for Ziggy)
COPY --from=php-dependencies /app/vendor ./vendor

# Copy source files
COPY puptas/ .

# Build frontend assets
RUN npm run build


# =============================================================================
# Stage 3: PHP + Apache Production Setup
# =============================================================================
FROM php:8.4-apache AS backend

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        zip \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd

# Enable Apache modules
RUN a2enmod rewrite \
    && a2enmod headers \
    && a2enmod ssl

# Configure Apache for production
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
        /etc/apache2/conf-available/*.conf

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Use existing www-data user (already exists in php:8.2-apache image)
RUN chown -R www-data:www-data /var/www

WORKDIR /var/www/html

# Copy Laravel application files
COPY puptas/ .

# Copy built frontend assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

# -----------------------------------------------------------------------------
# Laravel Production Setup
# -----------------------------------------------------------------------------

# Set proper permissions
RUN chown -R www-data:www-data storage bootstrap/cache public/uploads
RUN chmod -R 775 storage bootstrap/cache
RUN chmod -R 755 public/uploads 2>/dev/null || true
RUN chmod 644 .env 2>/dev/null || true

# Install PHP dependencies (production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Laravel optimization commands for production
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Create opcache directory and set permissions
RUN mkdir -p /var/www/html/storage/framework/cache/data \
    && chown -R www-data:www-data /var/www/html/storage/framework/cache/data

# Expose HTTP port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache in foreground
CMD ["apache2-foreground"]
