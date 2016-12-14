.PHONY: all

all: vendor

composer.lock: composer.json
	# Updating Dependencies with Composer
	composer update -n -o

vendor: composer.lock
	# Installing Dependencies with Composer
	composer install -n -o
