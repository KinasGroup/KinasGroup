FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx curl git unzip libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mysqli

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html/
WORKDIR /var/www/html

# RUN COMPOSER INSTALL - THIS WAS MISSING!
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create uploads directory with correct permissions
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 775 /var/www/html/uploads

# Copy nginx config
COPY nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 8080

CMD sh -c "php-fpm -D && nginx -g 'daemon off;'"
