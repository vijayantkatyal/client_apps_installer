FROM php:8.1-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies (using Alpine approach)
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    zip \
    git \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions (single command like reference)
RUN docker-php-ext-install mysqli pdo pdo_mysql bcmath

# Enable Apache modules
RUN a2enmod rewrite

# Configure PHP
RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize = 100M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini

# Configure Apache
RUN echo "<Directory /var/www/html>" > /etc/apache2/sites-available/000-default.conf \
    && echo "    AllowOverride All" >> /etc/apache2/sites-available/000-default.conf \
    && echo "    Require all granted" >> /etc/apache2/sites-available/000-default.conf \
    && echo "</Directory>" >> /etc/apache2/sites-available/000-default.conf

# Create storage directories with proper permissions
RUN mkdir -p /var/www/html/storage \
    && mkdir -p /var/www/html/storage/license \
    && mkdir -p /var/www/html/storage/temp \
    && mkdir -p /var/www/html/storage/backups

# Copy installer files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/storage

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
