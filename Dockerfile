FROM php:8.2-apache

# Install system tools FIRST
RUN apt-get update && apt-get install -y zip unzip git curl

# Fix MPM AFTER apt (apt may re-enable event/worker)
RUN cd /etc/apache2/mods-enabled && \
    rm -f mpm_event.load mpm_event.conf \
          mpm_worker.load mpm_worker.conf \
          mpm_prefork.load mpm_prefork.conf && \
    ln -s ../mods-available/mpm_prefork.load mpm_prefork.load && \
    ln -s ../mods-available/mpm_prefork.conf mpm_prefork.conf

RUN a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader
RUN chown -R www-data:www-data /var/www/html
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

CMD ["apache2-foreground"]