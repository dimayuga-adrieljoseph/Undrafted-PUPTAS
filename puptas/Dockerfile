# Use PHP 8.2 with FPM
FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nodejs \
    npm \
    git \
    unzip \
    libzip-dev \
    icu-dev \
    tesseract-ocr \
    tesseract-ocr-data-eng \
    ghostscript \
    curl

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql zip intl bcmath

# Install Composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files first (for layer caching)
COPY puptas/composer.json puptas/composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy application files from puptas directory
COPY puptas/ .

# Install Node dependencies and build assets
RUN npm ci && npm run build

# Set permissions
RUN chmod -R 755 storage bootstrap/cache && \
    chown -R www-data:www-data /var/www

# Generate Laravel application key
RUN php artisan key:generate --force

# Cache Laravel configuration for production
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Expose the port Railway will use
EXPOSE 8080

# Start the Laravel application
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8080"]
