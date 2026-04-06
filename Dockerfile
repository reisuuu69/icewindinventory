FROM php:8.2-apache

# Disable conflicting MPMs and enable prefork (required for PHP)
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork

# Enable rewrite
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

# Start Apache
CMD ["apache2-foreground"]
