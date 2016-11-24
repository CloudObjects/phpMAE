FROM cloudobjects/php-app-base
MAINTAINER "Lukas Rosenstock"

# Add application code
ADD / /var/www/app/

# Launch init script
CMD ["/bin/sh", "/var/www/app/init.sh"]