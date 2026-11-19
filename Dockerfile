# Base PHP + Composer + Node.js
FROM php:8.6.0RC5-fpm

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

# PHP config: the base image activates no php.ini, so PHP falls back to
# compiled-in defaults that print errors to output
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Loaded after the base image's docker-php-ext-opcache.ini, which only registers
# the extension and leaves it on stock defaults
COPY opcache.ini "$PHP_INI_DIR/conf.d/zz-opcache.ini"

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Install Node.js (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP dependencies. Scripts and autoloader are deferred because
# post-autoload-dump runs `artisan package:discover`, which needs the full app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-dev --no-scripts --no-autoloader

# Installed before the source copy so editing source does not reinstall
# node_modules on every deploy
COPY package.json package-lock.json ./
RUN npm ci

# Copy application files
COPY . .

RUN composer dump-autoload --no-interaction --no-dev --optimize

# Copy Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

# Copy Supervisor config
COPY supervisord.conf /etc/supervisord.conf

# OptimizedAppCheck reports unhealthy unless config, routes and events are cached.
# These are cached individually rather than via `optimize`, whose package hooks
# (spatie/laravel-data) write to the cache store, which has no database at build time
RUN php artisan storage:link \
    && php artisan ziggy:generate \
    && php artisan config:cache \
    && php artisan event:cache \
    && php artisan route:cache

RUN npm run build:ssr

# COPY writes the tree as root, so the php-fpm pool user is granted access to the
# only two paths it writes to at runtime. Blade compiles on demand because there
# is no view:cache, and the volume mount masks anything cached here at build time
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Expose port
EXPOSE 80

# Start services with Supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# Probes the framework health endpoint rather than running the spatie checks:
# those persist a row per check per run, which at this interval was the largest
# table in the database. They now run on the schedule instead.
HEALTHCHECK --interval=30s --timeout=10s --retries=3 \
    CMD curl -fsS http://localhost/up || exit 1
