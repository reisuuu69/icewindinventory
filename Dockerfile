FROM php:8.2-apache

RUN apt-get update && apt-get install -y zip unzip git curl

# Directly write the MPM conf instead of relying on symlinks
RUN find /etc/apache2 -name "mpm_*.load" -o -name "mpm_*.conf" | xargs rm -f && \
    echo "LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so" \
    > /etc/apache2/mods-enabled/mpm_prefork.load && \
    cp /etc/apache2/mods-available/mpm_prefork.conf \
       /etc/apache2/mods-enabled/mpm_prefork.conf

RUN a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader
RUN chown -R www-data:www-data /var/www/html
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

CMD ["apache2-foreground"]