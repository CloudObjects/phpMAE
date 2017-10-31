<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use ML\JsonLD\Node;
use ML\IRI\IRI;
use Pimple\Container;
use CloudObjects\SDK\ObjectRetriever, CloudObjects\SDK\NodeReader, CloudObjects\SDK\COIDParser;
use CloudObjects\SDK\WebAPI\APIClientFactory;
use CloudObjects\PhpMAE\PhpMAEException;

/**
 * The DependencyInjector adds all the dependencies specified for a PHP class
 * to a Pimple container.
 */
class DependencyInjector {

    /**
     * Process dependencies.
     *
     * @param Node $object The object representing the PHP class.
     * @param Container $container The container on which the dependencies should be added.
     * @param ObjectRetriever $retriever The retriever pool.
     */
    public static function processDependencies(Node $object, Container $container, ObjectRetrieverPool $retrieverPool) {
        $dependencies = $object->getProperty('coid://phpmae.cloudobjects.io/hasDependency');
        if (!isset($dependencies)) return; // no dependencies to process
        if (!is_array($dependencies)) $dependencies = array($dependencies);

        $reader = new NodeReader([
            'prefixes' => [ 'phpmae' => 'coid://phpmae.cloudobjects.io/' ]
        ]);

        foreach ($dependencies as $d) {
            if (!$d->getProperty('coid://phpmae.cloudobjects.io/hasKey'))
                throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: no key!");

            if ($reader->hasType($d, 'phpmae:StaticTextDependency')) {
                // Static Text Dependency
                $value = $reader->getFirstValueString($d, 'phpmae:hasValue');
                if (!isset($value))
                    throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: StaticTextDependency without value!");
                $dependency = function() use ($value) {
                    return $value;
                };
            } else
            if ($reader->hasType($d, 'phpmae:WebAPIDependency')) {
                // Web API Dependency
                $apiCoid = $reader->getFirstValueString($d, 'phpmae:hasAPI');
                if (!isset($apiCoid))
                    throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: WebAPIDependency without API!");
                $dependency = function() use ($apiCoid, $retrieverPool, $object) {
                    $namespaceCoid = COIDParser::getNamespaceCOID(new IRI($object->getId()));
                    $apiCoid = new IRI($apiCoid);                    
                    return APIClientFactory::createClient($retrieverPool->getBaseObjectRetriever()->get($apiCoid),
                        $retrieverPool->getObjectRetriever($apiCoid->getHost())->get($namespaceCoid));
                };
            } else
                throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: unknown type!");

            $container[$reader->getFirstValueString($d, 'phpmae:hasKey')] = $dependency;
        }
    }
}
