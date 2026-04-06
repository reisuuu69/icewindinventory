FROM php:8.2-apache

# Remove all enabled MPM configs first
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load
RUN rm -f /etc/apache2/mods-enabled/mpm_*.conf

# Enable ONLY prefork (needed for PHP)
RUN a2enmod mpm_prefork

# Enable rewrite
RUN a2enmod rewrite

# Copy files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Start Apache
CMD ["apache2-foreground"]
