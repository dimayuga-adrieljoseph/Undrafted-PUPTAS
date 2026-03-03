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
# Stage 3: Production Image (PHP-FPM + Nginx)
# =============================================================================
FROM php:8.4-fpm

# Install system dependencies + nginx
RUN apt-get update && apt-get install -y \
    nginx \
    libzip-dev libonig-dev libxml2-dev \
    libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_mysql zip mbstring exif pcntl bcmath gd


RUN sed -i 's|listen = .*|listen = 9000|' /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Copy Laravel app (with vendor)
COPY --from=php-dependencies /app /var/www/html

# Copy built frontend
COPY --from=frontend /app/public/build ./public/build

# Fix permissions
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy Nginx config
COPY docker/nginx.conf /etc/nginx/nginx.conf

# Copy Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord"]

# # =============================================================================
# # Stage 3: Production Image
# # =============================================================================
# FROM php:8.4-apache

# RUN apt-get update && apt-get install -y \
#     libzip-dev libonig-dev libxml2-dev \
#     libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
#     && rm -rf /var/lib/apt/lists/*

# RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
#     && docker-php-ext-install \
#         pdo pdo_mysql zip mbstring exif pcntl bcmath gd

# # -----------------------------
# # Basic Apache setup (minimal)
# # -----------------------------
# RUN a2enmod rewrite headers ssl

# ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
# RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
#     /etc/apache2/sites-available/*.conf \
#     && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
#         /etc/apache2/apache2.conf \
#         /etc/apache2/conf-available/*.conf

# WORKDIR /var/www/html

# # Copy Laravel app INCLUDING vendor
# COPY --from=php-dependencies /app /var/www/html

# # Copy built frontend assets
# COPY --from=frontend /app/public/build ./public/build

# # -----------------------------
# # COMPLETE Laravel Permissions (Fix 1)
# # -----------------------------
# RUN mkdir -p \
#     storage/framework/{sessions,views,cache,maintenance} \
#     storage/logs \
#     bootstrap/cache && \
#     chown -R www-data:www-data \
#     storage \
#     bootstrap/cache && \
#     chmod -R 775 \
#     storage \
#     bootstrap/cache && \
#     chmod -R 755 \
#     storage/framework \
#     storage/logs

# # Optimize Laravel
# RUN php artisan config:cache && \
#     php artisan route:cache && \
#     php artisan view:cache

# # -----------------------------
# # Custom Entrypoint
# # -----------------------------
# COPY docker-entrypoint.sh /usr/local/bin/
# RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# EXPOSE 80
# CMD ["/usr/local/bin/docker-entrypoint.sh"]
