FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx curl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /var/www/html/
WORKDIR /var/www/html

RUN docker-php-ext-install pdo_mysql mysqli

COPY nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 8080

CMD sh -c "php-fpm -D && nginx -g 'daemon off;'"
