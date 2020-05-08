FROM cloudobjects/php-app-base:latest

# Add application code
ADD / /var/www/app/

# Launch init script
CMD ["/bin/sh", "/var/www/app/init.sh"]