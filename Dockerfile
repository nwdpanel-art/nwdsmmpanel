FROM php:8.2-apache
RUN docker-php-ext-install mysqli pdo_mysql && a2enmod rewrite headers
RUN sed -ri 's/Listen 80/Listen ${PORT}/; ' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf
ENV PORT=10000
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/uploads
EXPOSE 10000
