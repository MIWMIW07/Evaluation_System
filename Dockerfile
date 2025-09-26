# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (including PostgreSQL support)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql pdo_pgsql zip

# Install Composer (latest version from official image)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite and headers, set ServerName
RUN a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Create custom Apache configuration to handle .htaccess properly
RUN echo "<Directory /var/www/html>" > /etc/apache2/conf-available/custom.conf \
    && echo "    AllowOverride All" >> /etc/apache2/conf-available/custom.conf \
    && echo "    Require all granted" >> /etc/apache2/conf-available/custom.conf \
    && echo "</Directory>" >> /etc/apache2/conf-available/custom.conf \
    && a2enconf custom

# Copy application files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Install PHP dependencies with Composer (if composer.json exists)
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create necessary directories if they don't exist
RUN mkdir -p /var/www/html/reports /var/www/html/credentials \
    && chown -R www-data:www-data /var/www/html/reports /var/www/html/credentials \
    && chmod 755 /var/www/html/reports /var/www/html/credentials

# Expose port 80
EXPOSE 80

# Health check to ensure container is running properly
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD curl -f http://localhost/test_connection.php || exit 1

# Start Apache in foreground
CMD ["apache2-foreground"]
