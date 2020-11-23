FROM cloudobjects/php-app-base:latest

# Add application code
ADD / /var/www/app/

# Add an executable to the Docker container for CLI use
RUN mv /var/www/app/phpmae.docker /usr/local/bin/phpmae && chmod +x /usr/local/bin/phpmae

# Launch init script
CMD ["/bin/sh", "/var/www/app/init.sh"]