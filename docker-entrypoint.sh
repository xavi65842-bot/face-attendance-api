#!/bin/bash
set -e

PORT="${PORT:-8080}"

echo "Configuring Apache to listen on port 80, 8080, and ${PORT}..."

# Configure ports.conf to listen on 80, 8080, and dynamic $PORT
cat <<EOF > /etc/apache2/ports.conf
Listen 80
Listen 8080
Listen ${PORT}
EOF

# Configure VirtualHost to accept requests on ANY port
cat <<EOF > /etc/apache2/sites-available/000-default.conf
<VirtualHost *:80 *:8080 *:${PORT} *:* >
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

exec "$@"
