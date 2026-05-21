FROM php:8.2-apache

# Install PostgreSQL development libraries and PHP pgsql extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite for .htaccess support
RUN a2enmod rewrite

# Copy application files to Apache root
COPY . /var/www/html/

# Set secure permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose default Apache port
EXPOSE 80
