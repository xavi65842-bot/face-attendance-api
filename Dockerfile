FROM php:8.2-cli

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

WORKDIR /var/www/html
COPY . /var/www/html/

# Permissions for uploaded images
RUN mkdir -p /var/www/html/uploads && chmod -R 777 /var/www/html/uploads

ENV PORT=8080
EXPOSE 8080
EXPOSE 80

# Start PHP built-in server bound to 0.0.0.0 and Railway's $PORT
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
