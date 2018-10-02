# Write configuration file

echo "<?php return array(" \
	" 'uploads' => false, " \
	" 'cache_dir' => __DIR__.'/cache', " \
	" 'uploads_dir' => __DIR__.'/uploads', " \
	" 'mode' => 'hybrid', " \
	" 'client_authentication' => '$CLIENT_AUTH', " \
	" 'CloudObjects\SDK\ObjectRetriever' => function() { " \
	"   return new CloudObjects\SDK\ObjectRetriever([ " \
	"    'auth_ns' => '$CO_AUTH_NS', " \
	"    'auth_secret' => '$CO_AUTH_SECRET', " \
	"    'static_config_path' => __DIR__.'/uploads/config' " \
	"   ]); " \
	" } " \
    " ); " > /var/www/app/config.php

# Make cache folder

mkdir cache
chown lighttpd:lighttpd cache

# Run start script from parent container

sh /tmp/start.sh