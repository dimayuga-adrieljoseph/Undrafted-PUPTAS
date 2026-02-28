# Use PHP 8.4 CLI for Laravel
FROM php:8.4-cli

# Install system dependencies needed for PHP extensions and Node
RUN apt-get update && apt-get install -y \
    nodejs \
    npm \
    git \
    unzip \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install required PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql zip intl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy the full Laravel project
COPY . .

# Ensure storage directories exist and have proper permissions
RUN mkdir -p storage/framework/{sessions,views,cache} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Build frontend assets
RUN npm ci && npm run build

# Expose port for Railway
EXPOSE 8080

# Use start script for runtime tasks (caches, migrations, server)
COPY start.sh /start.sh
RUN chmod +x /start.sh
CMD ["/start.sh"]