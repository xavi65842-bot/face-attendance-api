FROM php:8.2-apache

# Install PDO MySQL, mysqli, and essential extensions
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

WORKDIR /var/www/html
COPY . /var/www/html/

# Permissions for uploaded images
RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

# Add entrypoint script to bind dynamic Railway PORT at runtime
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENV PORT=80
EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
