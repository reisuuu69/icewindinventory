FROM php:8.2-apache

# Copy files
COPY . /var/www/html/

# Enable Apache rewrite
RUN a2enmod rewrite

# Fix permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port (IMPORTANT for Railway)
EXPOSE 80
