FROM php:8.2-cli

# Set working directory
WORKDIR /app

# Copy files
COPY . .

# Expose Railway port
EXPOSE 8080

# Start PHP built-in server
CMD php -S 0.0.0.0:$PORT -t .
