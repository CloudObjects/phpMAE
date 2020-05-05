<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\DI;

use ML\JsonLD\Node;
use ML\IRI\IRI;
use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use DI\ContainerBuilder;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Cache\FilesystemCache;
use Psr\Http\Message\RequestInterface;
use CloudObjects\SDK\ObjectRetriever, CloudObjects\SDK\NodeReader, CloudObjects\SDK\COIDParser;
use CloudObjects\SDK\AccountGateway\AccountContext;
use CloudObjects\SDK\WebAPI\APIClientFactory;
use CloudObjects\SDK\Common\CryptoHelper;
use CloudObjects\PhpMAE\ObjectRetrieverPool, CloudObjects\PhpMAE\ClassRepository,
    CloudObjects\PhpMAE\ErrorHandler, CloudObjects\PhpMAE\Engine,
    CloudObjects\PhpMAE\ConfigLoader, CloudObjects\PhpMAE\TwigTemplate,
    CloudObjects\PhpMAE\TwigTemplateFactory;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;
use CloudObjects\PhpMAE\Injectables\CacheBridge;

/**
 * The DependencyInjector returns all the dependencies specified for a PHP class.
 */
class DependencyInjector {

    private $retrieverPool;
    private $classRepository;
    private $container;

    public function __construct(ObjectRetrieverPool $retrieverPool, ClassRepository $classRepository,
            ContainerInterface $container) {
        
        $this->retrieverPool = $retrieverPool;
        $this->classRepository = $classRepository;
        $this->container = $container;
    }

    /**
     * Checks whether the request comes from a CloudObjects Account Gateway and,
     * if so, configures an AccountContext for the class to use.
     */
    private function configureAccountGateway(RequestInterface $request) {
        $definitions = [];

        if ($request->hasHeader('C-AAUID')
				&& $request->hasHeader('C-Access-Token')) {
			
			$definitions[AccountContext::class] = function() use ($request) {
                $context = AccountContext::fromPsrRequest($request);

                if ($this->container->get('agws.data_cache') == 'file') {
                    // Enable filesystem cache for account data
                    $cache = new FilesystemCache($this->container->get('cache_dir') . '/acct');
                    $context->getDataLoader()->setCache($cache);
                }

                return $context;
            };
		}		
        
        return $definitions;
    }

    /**
     * Get all dependencies for injection.
     *
     * @param Node $object The object representing the PHP class.
     * @param array $additionalDefinitions Additional definitions to add to the container
     */
    public function getDependencies(Node $object, array $additionalDefinitions = []) {
        $reader = new NodeReader([
            'prefixes' => [ 'phpmae' => 'coid://phpmae.cloudobjects.io/' ]
        ]);

        $dependencies = $reader->getAllValuesNode($object, 'phpmae:hasDependency');
        
        $objectCoid = new IRI($object->getId());
        $namespaceCoid = COIDParser::getNamespaceCOID($objectCoid);        

        $definitions = [
            'cookies' => \DI\create(ArrayCollection::class),
            ObjectRetriever::class => function() use ($namespaceCoid) {
                // Get or create an object retriever with the identity of the namespace of this object
                return $this->retrieverPool->getObjectRetriever($namespaceCoid->getHost());
            },
            DynamicLoader::class => function() {
                return new DynamicLoader($this->retrieverPool->getBaseObjectRetriever(),
                    $this->classRepository);
            },
            ConfigLoader::class => function(ContainerInterface $c) use ($objectCoid, $additionalDefinitions) {
                // Get a ConfigLoader that allows the class to read the configuration
                // of various objects, including itself and additional definitions
                $configDefinitions = [
                    'self' => $objectCoid
                ];
                foreach ($additionalDefinitions as $key => $value)
                    if (is_a($value, IRI::class))
                        $configDefinitions[$key] = $value;

                if (isset($additionalDefinitions[RequestInterface::class])) {
                    $request = $c->get(RequestInterface::class);
                    if ($request->hasHeader('C-Accessor'))
                        $configDefinitions['accessor'] =
                            new IRI($request->getHeaderLine('C-Accessor'));
                }

                return new ConfigLoader($configDefinitions, $c->get(ObjectRetriever::class));
            },
            TwigTemplateFactory::class => function() use ($object) {
                return new TwigTemplateFactory($this->classRepository
                    ->getCustomFilesCachePath($object));
            },
            CryptoHelper::class => function(ContainerInterface $c) use ($namespaceCoid) {
                return new CryptoHelper($c->get(ObjectRetriever::class), $namespaceCoid);
            },
            CacheInterface::class => function(ContainerInterface $c) use ($namespaceCoid) {
                // Enable filesystem cache for custom data
                return new CacheBridge(
                    new FilesystemCache($this->container->get('cache_dir') . '/custom'),
                    $namespaceCoid->getHost()
                );
            }
        ];

        $definitions = array_merge($definitions, $additionalDefinitions);

        if (isset($definitions[RequestInterface::class])) {                        
            $definitions = array_merge($definitions, $this->configureAccountGateway($definitions[RequestInterface::class]));
        }

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

                $keyedDependency = function() use ($apiCoid, $namespaceCoid, $object) {
                    $apiCoid = new IRI($apiCoid);
                    $apiClientFactory = new APIClientFactory(
                        $this->retrieverPool->getObjectRetriever($namespaceCoid->getHost()),
                        $namespaceCoid
                    );
                    return $apiClientFactory->getClientWithCOID($apiCoid);
                };
            } else
            if ($reader->hasType($d, 'phpmae:ClassDependency')) {
                // Class Dependency
                $classCoid = $reader->getFirstValueIRI($d, 'phpmae:hasClass');
                if (!isset($classCoid))
                    throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: ClassDependency without class!");

                // TODO: can we optimize this? only create dependency container in inject function
                $dependencyContainer = $this->classRepository
                    ->createInstance($this->retrieverPool->getBaseObjectRetriever()->getObject($classCoid), null, [ 'callerClass' => $objectCoid ]);
                $keyedDependency = function() use ($dependencyContainer) {
                    return $dependencyContainer->get(Engine::SKEY);
                };

                // Also add with classname to allow constructor autowiring
                $definitions[$this->classRepository->coidToClassName($classCoid)] = $keyedDependency;
            } else
            if ($reader->hasType($d, 'phpmae:TwigTemplateDependency')) {
                // Twig Template Dependency
                $filename = $reader->getFirstValueString($d, 'phpmae:usesAttachedTwigFile');
                if (!isset($filename))
                    throw new PhpMAEException("<".$object->getId()."> has an invalid dependency: TwigTemplateDependency without attachment!");
                
                $keyedDependency = function() use ($object, $objectCoid, $filename) {
                    $content = $this->retrieverPool->getBaseObjectRetriever()
                        ->getAttachment($objectCoid, $filename);
                    $cachePath = $this->classRepository->getCustomFilesCachePath($object);
                    return new TwigTemplate($filename, $content, $cachePath);         
                };
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
