FROM cloudobjects/php-app-base:latest

# Add application code
ADD / /var/www/app/

# Add an executable to the Docker container for CLI use
RUN mv /var/www/app/phpmae.docker /usr/local/bin/phpmae && chmod +x /usr/local/bin/phpmae

# Have default config.php ready for CLI where init may not run
RUN cp /var/www/app/config.php.default /var/www/app/config.php

# Launch init script
CMD ["/bin/sh", "/var/www/app/init.sh"]