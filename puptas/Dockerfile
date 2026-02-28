# Use PHP 8.2 with FPM
FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    nodejs \
    npm \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    ghostscript \
    curl \
    libfreetype6-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql zip intl bcmath gd

# Install Composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy entire project FIRST
COPY . .

RUN mkdir -p storage/framework/sessions \
    storage/framework/views \
    storage/framework/cache \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Build frontend
RUN npm ci && npm run build

EXPOSE 8080
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
