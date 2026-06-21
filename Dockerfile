FROM php:8.2-apache

# Install PostgreSQL development libraries and PHP pgsql extension (termasuk pdo_pgsql)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Paksa matikan mpm_event, hidupkan mpm_prefork, dan aktifkan modul untuk .htaccess
RUN a2dismod mpm_event || true \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && a2enmod deflate \
    && a2enmod expires \
    && a2enmod headers

# Copy application files to Apache root
COPY . /var/www/html/

# Set secure permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose default Apache port
EXPOSE 80