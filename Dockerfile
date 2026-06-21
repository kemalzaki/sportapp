FROM php:8.2-apache

# Install PostgreSQL development libraries and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Bersihkan total konfigurasi MPM bertabrakan & aktifkan modul wajib
RUN rm -f /etc/apache2/mods-enabled/mpm_*.load \
    && rm -f /etc/apache2/mods-enabled/mpm_*.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && a2enmod deflate \
    && a2enmod expires \
    && a2enmod headers

# Ubah konfigurasi Apache agar mendengarkan Port dinamis dari Railway
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/g' /etc/apache2/sites-available/000-default.conf

# Copy application files to Apache root
COPY . /var/www/html/

# Set secure permissions for Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Ganti EXPOSE agar menggunakan variabel dinamis
EXPOSE ${PORT}