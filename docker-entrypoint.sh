#!/bin/bash
set -e

# Railway provides dynamic $PORT (or defaults to 80)
PORT="${PORT:-80}"

echo "Starting Apache on port $PORT..."

# Dynamically set Apache port
sed -i "s/Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:.*/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Enable AllowOverride All in /var/www/html
sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

exec "$@"
