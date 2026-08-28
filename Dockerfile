FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install zip pdo pdo_mysql

# Enable Apache mod_rewrite for nice URLs if needed
RUN a2enmod rewrite

# Copy composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files into the container
COPY . /var/www/html/

# Install composer dependencies
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# Fix permissions
RUN chown -R www-data:www-data /var/www/html/

# Expose port 80 for Render
EXPOSE 80
