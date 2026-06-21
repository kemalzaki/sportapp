FROM php:8.2-apache

# Install PostgreSQL development libraries and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Aktifkan modul dasar yang dibutuhkan .htaccess
RUN a2enmod mpm_prefork \
    && a2enmod rewrite \
    && a2enmod deflate \
    && a2enmod expires \
    && a2enmod headers

# Copy application files
COPY . /var/www/html/

# Set secure permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Salin script entrypoint dan berikan izin eksekusi
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

# Jalankan entrypoint kustom kita
ENTRYPOINT ["/entrypoint.sh"]