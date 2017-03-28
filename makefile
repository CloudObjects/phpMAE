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

phpmae.phar: phpmae.php vendor RoboFile.php robo.phar
	# Building archive with robo
	php robo.phar phar