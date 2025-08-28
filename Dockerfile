# Base PHP + Composer + Node.js
FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update \
    && apt-get install -y \
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
    && docker-php-ext-install pdo pdo_pgsql mbstring zip exif pcntl bcmath \
    && apt-get clean

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer


# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Install Node.js (LTS) and install dependencies
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && curl -L https://npmjs.org/install.sh | sh \
    && apt-get clean \
    && composer install --no-interaction --prefer-dist --optimize-autoloader \ 
    && php artisan storage:link \    
    && npm install \
    && php artisan ziggy:generate \
    && npm run build:ssr

# Create Laravel cache & logs folders
RUN mkdir -p storage/logs bootstrap/cache && \
    chown -R www-data:www-data /var/www

# Copy Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

# Copy Supervisor config to manage multiple services
COPY supervisord.conf /etc/supervisord.conf

# Expose ports
EXPOSE 80

# Start services with Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
