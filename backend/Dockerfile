FROM php:8.2-fpm

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    librabbitmq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock* ./

# Install PHP dependencies
RUN composer install --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress --no-suggest

# Copy application code
COPY . .

# Generate autoloader
RUN composer dump-autoload --optimize

# Set permissions
RUN mkdir -p var/cache var/log && \
    chown -R www-data:www-data . && \
    chmod -R 755 var

# Expose port
EXPOSE 8000

# Set environment
ENV APP_ENV=dev

# Default command
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
