.PHONY: all clean

all: phpmae.phar

clean:
	rm *.phar

composer.lock: composer.json
	# Updating Dependencies with Composer
	composer update -n -o

vendor: composer.lock
	# Installing Dependencies with Composer
	composer install -n -o

robo.phar:
	# Get a copy of robo
	wget http://robo.li/robo.phar

config.php: config.php.default
	# Use .default if no other config provided
	cp config.php.default config.php

phpmae.phar: phpmae.php config.php vendor RoboFile.php robo.phar
	# Building archive with robo
	php robo.phar phar