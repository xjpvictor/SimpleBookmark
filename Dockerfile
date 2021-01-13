FROM php:7.0-apache
COPY ./ /var/www/html/
RUN chown www-data /var/www/html -R