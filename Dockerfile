FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx curl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/
WORKDIR /var/www/html

# The uploads/ folder isn't in the repo — it's created at runtime when an
# agent uploads listing images. Without this, it gets created (if at all)
# owned by root, and the php-fpm worker (www-data) can't write into it —
# uploads fail silently because the error is only logged, never shown.
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 775 /var/www/html/uploads

RUN docker-php-ext-install pdo_mysql mysqli

COPY nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 8080

CMD sh -c "php-fpm -D && nginx -g 'daemon off;'"
