FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx

WORKDIR /var/www/html

COPY config/nginx.conf /etc/nginx/nginx.conf
COPY config/php.ini /usr/local/etc/php/conf.d/app.ini
COPY config/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY public ./public
COPY src ./src
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /run/nginx /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/storage

EXPOSE 80

CMD ["entrypoint.sh"]