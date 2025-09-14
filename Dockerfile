# Use PHP with Apache
FROM php:8.2-apache

# Install required packages and PHP extensions for PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and headers
RUN a2enmod rewrite headers

# Set ServerName to prevent the warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Copy application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Create a simple startup script
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Set default port if not provided\n\
export PORT=${PORT:-80}\n\
echo "Starting Apache on port $PORT"\n\
\n\
# Update Apache port configuration\n\
echo "Listen $PORT" > /etc/apache2/ports.conf\n\
\n\
# Update virtual host configuration\n\
cat > /etc/apache2/sites-available/000-default.conf << EOF\n\
<VirtualHost *:$PORT>\n\
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
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n\
EOF\n\
\n\
# Start Apache in foreground\n\
exec apache2-foreground\n\
' > /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh

# Expose the port (Railway will override this)
EXPOSE ${PORT:-80}

# Use our custom start script
CMD ["/usr/local/bin/start.sh"]
