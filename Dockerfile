FROM php:8.2-apache

# Install system tools
RUN apt-get update && apt-get install -y zip unzip git curl

# Fix MPM after apt (apt may re-enable mpm_event)
RUN find /etc/apache2 -name "mpm_*.load" -o -name "mpm_*.conf" | xargs rm -f && \
    echo "LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so" \
    > /etc/apache2/mods-enabled/mpm_prefork.load

RUN a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader
RUN chown -R www-data:www-data /var/www/html

CMD ["bash", "-c", "ls /etc/apache2/mods-enabled/ | grep mpm && apache2-foreground"]
CMD ["apache2-foreground"]