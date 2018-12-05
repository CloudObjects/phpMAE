# Apply defaults for unset variables
if [ -z $MODE ]; then MODE=default; fi
if [ -z $CLIENT_AUTH ]; then CLIENT_AUTH=shared_secret:runclass; fi

# Write configuration file

echo "<?php return array(" \
	" 'uploads' => false, " \
	" 'cache_dir' => __DIR__.'/cache', " \
	" 'uploads_dir' => __DIR__.'/uploads', " \
	" 'mode' => '$MODE', " \
	" 'client_authentication' => '$CLIENT_AUTH', " \
	" 'client_authentication_must_be_secure' => false, " \
	" 'co.auth_ns' => '$CO_AUTH_NS', " \
	" 'co.auth_secret' => '$CO_AUTH_SECRET', " \
    " ); " > /var/www/app/config.php

# Make cache folder

mkdir /var/www/app/cache
chown lighttpd:lighttpd /var/www/app/cache

# Run start script from parent container

sh /tmp/start.sh