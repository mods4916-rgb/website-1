# Dockerfile for deploying the PHP site on Render (or any Docker host)
# This image uses Apache HTTPD with PHP 8.2 and enables SQLite.

FROM php:8.2-apache

# Enable required PHP extensions (PDO + SQLite)
RUN docker-php-ext-install pdo pdo_sqlite

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
