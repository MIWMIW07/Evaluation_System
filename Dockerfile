# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite and set ServerName
RUN a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configure Apache for Railway with dynamic port
RUN echo "<Directory /var/www/html>" > /etc/apache2/conf-available/railway.conf \
    && echo "    AllowOverride All" >> /etc/apache2/conf-available/railway.conf \
    && echo "    Require all granted" >> /etc/apache2/conf-available/railway.conf \
    && echo "</Directory>" >> /etc/apache2/conf-available/railway.conf \
    && a2enconf railway

# Copy application files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Install PHP dependencies with better error handling
RUN if [ -f "composer.json" ]; then \
        echo "Installing Composer dependencies..." && \
        composer install --no-dev --optimize-autoloader --no-interaction || \
        (echo "Composer install failed" && exit 1); \
    else \
        echo "No composer.json found, skipping dependency installation"; \
    fi

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/reports \
    && chown www-data:www-data /var/www/html/reports

# Create a startup script to handle dynamic port
RUN echo '#!/bin/bash' > /start.sh \
    && echo 'PORT=${PORT:-80}' >> /start.sh \
    && echo 'sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf' >> /start.sh \
    && echo 'sed -i "s/<VirtualHost \\*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf' >> /start.sh \
    && echo 'apache2-foreground' >> /start.sh \
    && chmod +x /start.sh

# Expose port (will be overridden by Railway)
EXPOSE 80

# Start with our custom script
CMD ["/start.sh"]
