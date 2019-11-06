.PHONY: all clean

all: stacks phpmae.phar

clean:
	# Delete PHAR and stacks
	rm *.phar
	rm -rf stacks

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

stacks: robo.phar vendor
	# Create stack directory
	mkdir stacks
	# Install default stack
	php robo.phar install:stack coid://phpmae.cloudobjects.io/DefaultStack

phpmae.phar: phpmae.php config.php vendor RoboFile.php robo.phar
	# Building archive with robo
	php robo.phar phar