# Use PHP with Apache
FROM php:8.1-apache

# Install required PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Create Apache configuration that respects PORT env var
RUN echo '<VirtualHost *:${PORT}>\n\
    DocumentRoot /var/www/html\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
    <Directory /var/www/html>\n\
        Options Indexes FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Update ports.conf to use PORT env var
RUN echo 'Listen ${PORT}' > /etc/apache2/ports.conf

# Create startup script
RUN echo '#!/bin/bash\n\
# Use Railway PORT or default to 80\n\
export PORT=${PORT:-80}\n\
echo "Starting Apache on port $PORT"\n\
# Start Apache in foreground\n\
apache2-foreground' > /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

# Expose port (Railway will override this)
EXPOSE ${PORT:-80}

# Use our custom start script
CMD ["/usr/local/bin/start.sh"]