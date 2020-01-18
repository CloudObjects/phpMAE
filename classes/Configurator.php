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
        // Object Retriever Definition
        $objectRetrieverDefinition = DI\autowire();
        $orConstructorParameters = [
            'cache_provider' => 'file',
            'cache_provider.file.directory' => $config['cache_dir'] . '/config',
            'static_config_path' => $config['uploads_dir'] . '/config',
            'logger' => DI\get(Logger::class)
        ];

        if (!isset($config['co.auth_ns']) && CredentialManager::isConfigured()) {
			// Access Object API via Account Gateway using local developer account
            $objectRetrieverDefinition->constructor($orConstructorParameters)
                ->method('setClient', function() {
                    return CredentialManager::getAccountContext()->getClient();
                }, '/ws/');
        } else {
            // Access Object API with specified namespace credentials
            $objectRetrieverDefinition->constructor(array_merge([
                'auth_ns' => $config['co.auth_ns'],
                'auth_secret' => $config['co.auth_secret'],
            ], $orConstructorParameters));
        }   

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
            ObjectRetriever::class => $objectRetrieverDefinition,
            ObjectRetrieverPool::class => DI\autowire()
                ->constructorParameter('baseHostname', @$config['co.auth_ns'])
                ->constructorParameter('options', [
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