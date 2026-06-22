FROM php:8.3-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    nginx \
    curl \
    git \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    libzip-dev

# Install GD extension (required for mPDF)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo_mysql \
    mysqli \
    mbstring \
    zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create all necessary directories with correct permissions
RUN mkdir -p /var/www/html/uploads/solar-reports \
    && mkdir -p /var/www/html/vendor/mpdf/mpdf/tmp \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/vendor/mpdf/mpdf/tmp \
    && chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/vendor/mpdf/mpdf/tmp

# Copy nginx config
COPY nginx.conf /etc/nginx/http.d/default.conf

# Expose port 8080
EXPOSE 8080

# Start PHP-FPM and Nginx
CMD php-fpm -D && nginx -g "daemon off;"
