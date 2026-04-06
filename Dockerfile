# Start from PHP 8.2 with Apache
FROM php:8.2-apache

# Install system tools for Composer
RUN apt-get update && apt-get install -y zip unzip git curl

# Enable Apache rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files (without vendor)
COPY . .

# Install PHP dependencies inside container
RUN composer install --no-dev --optimize-autoloader

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

# Start Apache
CMD ["apache2-foreground"]