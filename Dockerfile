# Base PHP + Composer + Node.js
FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    git \
    gnupg2 \
    libonig-dev \
    libpng-dev \
    libpq-dev \
    libxml2-dev \
    libzip-dev \
    nginx \
    supervisor \
    unzip \
    zip \
    bash \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip exif pcntl bcmath \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Install Node.js (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Create Laravel cache & logs folders
RUN mkdir -p storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www

# Copy application files
COPY . .

# Copy Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

# Copy Supervisor config
COPY supervisord.conf /etc/supervisord.conf

# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Laravel setup
RUN php artisan storage:link \
    && php artisan ziggy:generate \
    && php artisan optimize

# Install Node packages and build
RUN npm install \
    && npm run build:ssr

# Expose port
EXPOSE 80

# Start services with Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

HEALTHCHECK --interval=30s --timeout=30s --retries=3 CMD [ "php", "artisan", "health:check"]
