<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\DI;

use ML\JsonLD\Node;
use ML\IRI\IRI;
use DI\ContainerBuilder;
use CloudObjects\SDK\ObjectRetriever, CloudObjects\SDK\NodeReader, CloudObjects\SDK\COIDParser;
use CloudObjects\SDK\WebAPI\APIClientFactory;
use CloudObjects\PhpMAE\ObjectRetrieverPool, CloudObjects\PhpMAE\ClassRepository, CloudObjects\PhpMAE\ErrorHandler, CloudObjects\PhpMAE\Engine;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

/**
 * The DependencyInjector returns all the dependencies specified for a PHP class.
 */
class DependencyInjector {

    private $retrieverPool;
    private $classRepository;

    public function __construct(ObjectRetrieverPool $retrieverPool, ClassRepository $classRepository) {
        $this->retrieverPool = $retrieverPool;
        $this->classRepository = $classRepository;
    }

    /**
     * Get all dependencies for injection.
     *
     * @param Node $object The object representing the PHP class.
     */
    public function getDependencies(Node $object) {
        $reader = new NodeReader([
            'prefixes' => [ 'phpmae' => 'coid://phpmae.cloudobjects.io/' ]
        ]);

        $dependencies = $reader->getAllValuesNode($object, 'phpmae:hasDependency');
        
        $definitions = [];

        foreach ($dependencies as $d) {
            $keyedDependency = null;

            if (!$d->getProperty('coid://phpmae.cloudobjects.io/hasKey'))
                throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: no key!");

            if ($reader->hasType($d, 'phpmae:StaticTextDependency')) {
                // Static Text Dependency
                $value = $reader->getFirstValueString($d, 'phpmae:hasValue');
                if (!isset($value))
                    throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: StaticTextDependency without value!");

                $keyedDependency = $value;
            } else
            if ($reader->hasType($d, 'phpmae:WebAPIDependency')) {
                // Web API Dependency
                $apiCoid = $reader->getFirstValueString($d, 'phpmae:hasAPI');
                if (!isset($apiCoid))
                    throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: WebAPIDependency without API!");

                $keyedDependency = function() use ($apiCoid, $retrieverPool, $object) {
                    $namespaceCoid = COIDParser::getNamespaceCOID(new IRI($object->getId()));
                    $apiCoid = new IRI($apiCoid);                    
                    return APIClientFactory::createClient($this->retrieverPool->getBaseObjectRetriever()->get($apiCoid),
                        $retrieverPool->getObjectRetriever($apiCoid->getHost())->get($namespaceCoid));
                };
            } else
            if ($reader->hasType($d, 'phpmae:ClassDependency')) {
                // Class Dependency
                $classCoid = $reader->getFirstValueIRI($d, 'phpmae:hasClass');
                if (!isset($classCoid))
                    throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: ClassDependency without class!");

                $dependencyContainer = $this->classRepository->createInstance($this->retrieverPool->getBaseObjectRetriever()->getObject($classCoid),
                        $this->retrieverPool->getBaseObjectRetriever(), new ErrorHandler);
                $keyedDependency = function() use ($dependencyContainer) {
                    return $dependencyContainer->get(Engine::SKEY);
                };

                // Also add with classname to allow constructor autowiring
                $definitions[$this->classRepository->coidToClassName($classCoid)] = $keyedDependency;
            } else
                throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: unknown type!");

            $definitions[$reader->getFirstValueString($d, 'phpmae:hasKey')] = $keyedDependency;
        }

        return $definitions;
    }

    /**
     * Get a list of COIDs for class dependencies.
     *
     * @param Node $object The object representing the PHP class.
     */
    public function getClassDependencyList(Node $object) {
        $reader = new NodeReader([
            'prefixes' => [ 'phpmae' => 'coid://phpmae.cloudobjects.io/' ]
        ]);

        $dependencies = $reader->getAllValuesNode($object, 'phpmae:hasDependency');
        
        $list = [];

        foreach ($dependencies as $d) {            
            if ($reader->hasType($d, 'phpmae:ClassDependency')) {
                // Class Dependency
                $classCoid = $reader->getFirstValueIRI($d, 'phpmae:hasClass');
                if (!isset($classCoid))
                    throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: ClassDependency without class!");

                $list[] = $classCoid;
            }
        }

        return $list;
    }

}
