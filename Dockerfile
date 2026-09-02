FROM php:8.2-apache

# Install system dependencies & PHP extensions (pdo_mysql, mysqli, curl, gd, mbstring)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli gd mbstring

# Enable Apache mod_rewrite & headers
RUN a2enmod rewrite headers

# Copy application files
WORKDIR /var/www/html
COPY . /var/www/html/

# Create and set permissions for uploads directory
RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

# Configure Apache port mapping for Railway dynamic PORT
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf && \
    sed -i 's/:80/:${PORT}/' /etc/apache2/sites-available/000-default.conf

ENV PORT=80
EXPOSE 80
