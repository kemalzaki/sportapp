FROM php:8.2-apache

# Install PostgreSQL development libraries and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pgsql pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Bersihkan total modul MPM yang bertabrakan sejak fase build
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
    && rm -f /etc/apache2/mods-enabled/mpm_event.conf \
    && a2enmod mpm_prefork \
    && a2enmod rewrite \
    && a2enmod deflate \
    && a2enmod expires \
    && a2enmod headers

# Paksa Apache berjalan di port 8080 (Sangat stabil di Railway)
RUN sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:8080>/g' /etc/apache2/sites-available/000-default.conf

# Copy application files
COPY . /var/www/html/

# Set secure permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Salin script entrypoint dan berikan izin eksekusi wajib
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

# Eksekusi entrypoint kustom kita
ENTRYPOINT ["/entrypoint.sh"]