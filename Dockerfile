# Use PHP with Apache
FROM php:8.1-apache

# Install required packages and PHP extensions
RUN apt-get update && apt-get install -y \
    gettext-base \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and headers
RUN a2enmod rewrite headers

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Configure Apache to use Railway's PORT environment variable
RUN echo 'Listen ${PORT}' > /etc/apache2/ports.conf

# Create Apache virtual host configuration
RUN echo '<VirtualHost *:${PORT}>\n\
    DocumentRoot /var/www/html\n\
    ServerName localhost\n\
    \n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    \n\
    # Security headers\n\
    Header always set X-Content-Type-Options nosniff\n\
    Header always set X-Frame-Options DENY\n\
    Header always set X-XSS-Protection "1; mode=block"\n\
    \n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Create startup script that properly handles Railway's PORT
RUN echo '#!/bin/bash\n\
# Export Railway PORT environment variable\n\
export PORT=${PORT:-80}\n\
echo "Starting Apache on port $PORT"\n\
\n\
# Replace PORT placeholder in Apache config\n\
envsubst < /etc/apache2/ports.conf > /tmp/ports.conf && mv /tmp/ports.conf /etc/apache2/ports.conf\n\
envsubst < /etc/apache2/sites-available/000-default.conf > /tmp/000-default.conf && mv /tmp/000-default.conf /etc/apache2/sites-available/000-default.conf\n\
\n\
# Start Apache in foreground\n\
exec apache2-foreground' > /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

# Expose the port (Railway will override this)
EXPOSE ${PORT:-80}

# Use our custom start script
CMD ["/usr/local/bin/start.sh"]
