#!/bin/sh

# Ensure proper permissions if volumes are mounted externally
chown -R www-data:www-data /var/www/app/data 2>/dev/null || true
chown -R www-data:www-data /var/www/sites 2>/dev/null || true
chown -R www-data:www-data /etc/traefik/dynamic 2>/dev/null || true

# Start PHP-FPM in the background
php-fpm -D

# Start Nginx in the foreground
nginx -g 'daemon off;'
