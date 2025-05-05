FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

COPY . /var/www/html/

RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/g' /etc/apache2/sites-available/000-default.conf \
    && echo "DirectoryIndex WoW.html" >> /etc/apache2/apache2.conf

RUN a2enmod rewrite

RUN echo "display_errors = On" > /usr/local/etc/php/conf.d/error-reporting.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/error-reporting.ini

EXPOSE 8080

CMD ["apache2-foreground"]