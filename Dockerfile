# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install system tools
RUN apt-get update && apt-get install -y zip unzip git curl

# Remove any conflicting MPM modules first
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load
RUN rm -f /etc/apache2/mods-enabled/mpm_*.conf

# Enable only prefork MPM (required for PHP module)
RUN a2enmod mpm_prefork

# Enable rewrite module
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install dependencies inside Docker
RUN composer install --no-dev --optimize-autoloader

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

# Start Apache
CMD ["apache2-foreground"]