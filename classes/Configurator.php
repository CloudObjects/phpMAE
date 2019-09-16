<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use DI;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use CloudObjects\SDK\ObjectRetriever;

class Configurator {

    public static function getContainer(array $config) {
        // Create Dependency Injection Container
        $builder = new DI\ContainerBuilder();
        $builder->addDefinitions($config);
        $builder->addDefinitions([
            Logger::class => function(ContainerInterface $config) {
                $logger = new Logger('CO');
                switch ($config->get('log.target')) {
                    case "none":
                        $logger->pushHandler(new \Monolog\Handler\NullHandler);
                        break;
                    case "errorlog":
                        $logger->pushHandler(new \Monolog\Handler\ErrorLogHandler(0, $config->get('log.level')));
                        break;
                    default:
                        throw new \Exception("Invalid log.target!");
                }                
                return $logger;
            },
            ObjectRetrieverPool::class => DI\autowire()
                ->constructorParameter('baseHostname', $config['co.auth_ns'])
                ->constructorParameter('options', [
                    'cache_provider' => 'file',
                    'cache_provider.file.directory' => $config['cache_dir'] . '/config',
                    'static_config_path' => $config['uploads_dir'] . '/config',
                    'logger' => DI\get(Logger::class)
                ]),
            ObjectRetriever::class => DI\autowire()
                ->constructor([
                    'auth_ns' => $config['co.auth_ns'],
                    'auth_secret' => $config['co.auth_secret'],
                    'cache_provider' => 'file',
                    'cache_provider.file.directory' => $config['cache_dir'] . '/config',
                    'static_config_path' => $config['uploads_dir'] . '/config',
                    'logger' => DI\get(Logger::class)
                ])
        ]);

        $builder->enableCompilation($config['cache_dir']);
        return $builder->build();
    }

}