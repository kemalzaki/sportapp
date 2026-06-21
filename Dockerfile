FROM php:8.2-apache

# Install PostgreSQL development libraries and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Bersihkan total modul MPM yang bertabrakan secara paksa di level OS
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
    && rm -f /etc/apache2/mods-enabled/mpm_event.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && a2enmod deflate \
    && a2enmod expires \
    && a2enmod headers

# Copy seluruh file aplikasi ke Apache web root
COPY . /var/www/html/

# Set ownership dan permission yang aman
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Biarkan Apache berjalan di port default container (80)
EXPOSE 80