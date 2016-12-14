# Write configuration file

echo "<?php return array(" \
	" 'enable_vhost_controllers' => true, " \
	" 'exclude_vhosts' => ['localhost', 'phpmae.cloudobjects.io'], " \
	" 'debug' => false, " \
	" 'account_data_cache' => 'none', " \
	" 'object_cache' => 'file', " \
	" 'redis' => array(), " \
	" 'classes' => array( " \
	"	'cache_dir' => '/tmp/cache', " \
	"	'uploads_dir' => '/tmp/uploads' " \
	" ), " \
	" 'cloudobjects.auth_ns' => '$CO_AUTH_NS', " \
	" 'cloudobjects.auth_secret' => '$CO_AUTH_SECRET' " \
    " ); " > /var/www/app/config.php

# Run start script from parent container

sh /tmp/start.sh