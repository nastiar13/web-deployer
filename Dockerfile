FROM php:8.2-fpm-alpine

# Install Nginx, Git, Zip, Sqlite, and Sudo
RUN apk add --no-cache nginx git zip unzip sqlite sudo shadow

# Give www-data proper permissions for the OS
RUN usermod -u 1000 www-data && \
    mkdir -p /var/www/app /var/www/sites /etc/nginx/sites-enabled /etc/traefik/dynamic && \
    chown -R www-data:www-data /var/www /etc/nginx /etc/traefik/dynamic

# Allow PHP (www-data) to reload Nginx natively without password
RUN echo "www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx -s reload" >> /etc/sudoers
RUN echo "www-data ALL=(ALL) NOPASSWD: /bin/rm" >> /etc/sudoers

# Copy Nginx Configuration
COPY ./docker/nginx.conf /etc/nginx/nginx.conf

# Copy Application source
COPY . /var/www/app
RUN chown -R www-data:www-data /var/www/app

WORKDIR /var/www/app

# Copy Entrypoint
COPY ./docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

CMD ["entrypoint.sh"]
