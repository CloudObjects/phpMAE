<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

date_default_timezone_set('UTC');

require __DIR__.'/../vendor/autoload.php';
$config = require __DIR__.'/../config.php';

// Create Dependency Injection Container
$builder = new DI\ContainerBuilder();
$builder->addDefinitions($config);
$builder->addDefinitions([
    'CloudObjects\PhpMAE\ObjectRetrieverPool' => DI\autowire()
        ->constructorParameter('baseHostname', $config['co.auth_ns'])
        ->constructorParameter('options', [
            'cache_provider' => 'file',
            'cache_provider.file.directory' => $config['cache_dir'] . '/config',
            'static_config_path' => $config['uploads_dir'] . '/config'
        ]),
    'CloudObjects\SDK\ObjectRetriever' => DI\autowire()
        ->constructor([
            'auth_ns' => $config['co.auth_ns'],
            'auth_secret' => $config['co.auth_secret'],
            'cache_provider' => 'file',
            'cache_provider.file.directory' => $config['cache_dir'] . '/config',
            'static_config_path' => $config['uploads_dir'] . '/config'
        ])
]);
$builder->enableCompilation($config['cache_dir']);
$container = $builder->build();

$mode = $container->get('mode');
if ($mode == 'default') {
    // Default mode
    $container->get('CloudObjects\PhpMAE\Engine')
        ->run();
} elseif (substr($mode, 0, 7) == 'router:' || $mode == 'hybrid') {
    // Router or hybrid mode
    $container->get('CloudObjects\PhpMAE\Router')
        ->run();
} else {
    // Error
    die("Invalid mode!");
}