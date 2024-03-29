# Apply defaults for unset variables
if [ -z $MODE ]; then MODE=default; fi
if [ -z $CLIENT_AUTH ]; then CLIENT_AUTH=shared_secret:runclass; fi
if [ -z $INTERACTIVE ]; then INTERACTIVE=false; fi

# Write configuration file

echo "<?php return array(" \
	" 'uploads' => false, " \
	" 'interactive_run' => $INTERACTIVE, " \
	" 'cache_dir' => __DIR__.'/cache', " \
	" 'uploads_dir' => __DIR__.'/uploads', " \
	" 'mode' => '$MODE', " \
	" 'client_authentication' => '$CLIENT_AUTH', " \
	" 'client_authentication_must_be_secure' => false, " \
	" 'global_cors_origins' => 'https://cloudobjects.io', " \
	" 'co.auth_ns' => '$CO_AUTH_NS', " \
	" 'co.auth_secret' => '$CO_AUTH_SECRET', " \
	" 'agws.data_cache' => 'file', " \
	" 'log.target' => 'errorlog', " \
	" 'log.level' => Monolog\Logger::INFO, " \
    " ); " > /var/www/app/config.php

# Make cache folder

mkdir /var/www/app/cache
chown lighttpd:lighttpd /var/www/app/cache

# Run start script from parent container

sh /tmp/start.sh