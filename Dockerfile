# Dockerfile for deploying the PHP site on Render (or any Docker host)
# This image uses Apache HTTPD with PHP 8.2 and enables SQLite.

FROM php:8.2-apache

# Enable required PHP extensions (PDO SQLite)
# Note: PDO core ships with PHP; we only need to build pdo_sqlite and its system deps
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-configure pdo_sqlite --with-pdo-sqlite \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Optional: enable Apache mod_rewrite if you rely on .htaccess
RUN a2enmod rewrite

# Copy app into Apache docroot
COPY . /var/www/html

# Make data/ and uploads/ writable by the web server
# NOTE: On Render free tier, filesystem is ephemeral and can reset on redeploys.
RUN mkdir -p /var/www/html/data /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/data /var/www/html/uploads

# Allow .htaccess overrides (if you use any rewrite rules)
RUN sed -ri 's/AllowOverride None/AllowOverride All/i' /etc/apache2/apache2.conf || true

EXPOSE 80
CMD ["apache2-foreground"]
