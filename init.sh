# Write configuration file

echo "<?php return array(" \
	" 'uploads' => false, " \
	" 'cache_dir' => __DIR__.'/cache', " \
	" 'uploads_dir' => __DIR__.'/uploads', " \
	" 'mode' => 'hybrid', " \
	" 'client_authentication' => '$CLIENT_AUTH', " \
	" 'co.auth_ns' => '$CO_AUTH_NS', " \
	" 'co.auth_secret' => '$CO_AUTH_SECRET', " \
    " ); " > /var/www/app/config.php

# Make cache folder

mkdir /var/www/app/cache
chown lighttpd:lighttpd /var/www/app/cache

# Run start script from parent container

sh /tmp/start.sh