# Base PHP + Composer + Node.js
FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update \
    && apt-get install -y \
    curl \ 
    gnupg2 \
    git \
    unzip \
    zip \
    nginx \
    supervisor \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip exif pcntl bcmath

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && curl -L https://npmjs.org/install.sh | sh

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .


# Install Laravel and JS dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader \ 
    && php artisan storage:link \    
    && npm install \
    && php artisan ziggy:generate \
    && npm run build:ssr

# Create Laravel cache & logs folders
RUN mkdir -p storage/logs bootstrap/cache && \
    chown -R www-data:www-data /var/www

# Migrate database
RUN php artisan migrate --force

# Copy Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

# Copy Supervisor config to manage multiple services
COPY supervisord.conf /etc/supervisord.conf

# Expose ports
EXPOSE 80

# Start services with Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
