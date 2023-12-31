FROM php:8.2-apache
COPY server/ /var/www/server

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

EXPOSE 80