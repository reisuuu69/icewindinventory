FROM php:8.2-apache

# Install system tools
RUN apt-get update && apt-get install -y zip unzip git curl

# Brute-force fix: remove ALL mpm symlinks then only re-create prefork
RUN cd /etc/apache2/mods-enabled && \
    rm -f mpm_event.load mpm_event.conf \
          mpm_worker.load mpm_worker.conf \
          mpm_prefork.load mpm_prefork.conf && \
    ln -s ../mods-available/mpm_prefork.load mpm_prefork.load && \
    ln -s ../mods-available/mpm_prefork.conf mpm_prefork.conf

# Enable rewrite module
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /var/www/html

CMD ["apache2-foreground"]