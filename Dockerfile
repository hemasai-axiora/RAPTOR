FROM php:8.2-apache

# Install PDO MySQL extension and python3 (for ECS health check command)
RUN docker-php-ext-install pdo pdo_mysql && \
    apt-get update && apt-get install -y python3 && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Change Apache to listen on port 8000
RUN sed -i 's/Listen 80/Listen 8000/' /etc/apache2/ports.conf && \
    sed -i 's/:80/:8000/' /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html

# Create health check endpoint (plain PHP file at document root level)
RUN echo '<?php http_response_code(200); echo "OK"; ?>' > /var/www/html/health.php

# Configure Apache to allow .htaccess and set DocumentRoot properly
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/custom.conf && \
    a2enconf custom

# Set permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8000
