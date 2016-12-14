<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Silex\Application;
use Symfony\Component\HttpFoundation\Request, Symfony\Component\HttpFoundation\Response;
use ML\IRI\IRI, ML\JsonLD\Node;
use GuzzleHttp\Client;
use CloudObjects\SDK\AccountGateway\AccountContext, CloudObjects\SDK\ObjectRetriever, CloudObjects\SDK\COIDParser;
use Doctrine\Common\Cache\RedisCache;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class Runner {

 	/**
	 * Prepares the CloudObjects AccountGateway SDK if applicable.
	 */
	private static function prepareContext(Application $app, Request $request, array $config) {
		if ($request->headers->has('C-AAUID')
				&& $request->headers->has('C-Access-Token')) {

			$context = AccountContext::fromSymfonyRequest($request);
			$app['context'] = function() use ($context) {
				return $context;
			};
			$app['account'] = function() use ($context) {
				return $context->getAccount();
			};

			if (isset($config['account_data_cache']) && $config['account_data_cache']=='redis') {
				// Data Loader with Redis cache
				$redis = new \Redis();
				$redis->pconnect(
					isset($config['redis']) && isset($config['redis']['host']) ? $config['redis']['host'] : '127.0.0.1',
					isset($config['redis']) && isset($config['redis']['port']) ? $config['redis']['port'] : 6379);

				$cache = new RedisCache();
				$cache->setRedis($redis);

				$context->getDataLoader()->setCache($cache);
			}
		}

		if ($request->headers->has('C-Accessor')) {
			$accessorCoid = new IRI($request->headers->get('C-Accessor'));

			// Accessor object
			$app['accessor.object'] = function() use ($app, $accessorCoid) {
				return $app['cloudobjects']->getObject($accessorCoid);
			};

			// Accessor namespace object
			$app['accessor.namespace.object'] = function() use ($app, $accessorCoid) {
				return $app['cloudobjects']->getObject(COIDParser::getNamespaceCOID($accessorCoid));
			};
		}
	}

	/**
	 * Checks the controller object for any specified provider classes,
	 * loads them and mounts them into the Silex application. Also prepares
	 * generic providers.
	 */
	private static function prepareProvidersAndTemplates(Application $app, Node $object,
			ObjectRetriever $objectRetriever, ClassRepository $classRepository) {

		$objectIri = new IRI($object->getId());
		
		// Namespace object
		$app['namespace.object'] = function() use ($objectRetriever, $objectIri) {
			return $objectRetriever->get('coid://'.$objectIri->getHost());
		};

		// File Reader
		$app['files'] = function() use ($objectRetriever, $object, $classRepository) {
			return new FileReader($objectRetriever, $classRepository, $object);
		};

		// Template Engine
		$app['twig'] = function() use ($objectRetriever, $object, $classRepository) {
			return new \Twig_Environment(new TemplateLoader($objectRetriever,
				new IRI($object->getId())), array(
					'cache' => $classRepository->getCustomFilesCachePath($object)
			));
		};

		// CloudObjects Object Repository
		$app['cloudobjects'] = ($app['phpmae.identity'] == $objectIri->getHost())
			? $objectRetriever
			: function() use ($objectRetriever, $objectIri) {
				// Take the identity of the current controller when accessing CloudObjects API
				$config = $objectRetriever->getClient()->getConfig();
				$config['headers']['C-Act-As'] = $objectIri->getHost();

				$retriever = new ObjectRetriever();
				$retriever->setClient(new Client($config));
				return $retriever;
			};

		$app['config'] = function() use ($app) {
			return new ConfigHelper($app, array(
				'self.object', 'namespace.object',
				'accessor.object', 'accessor.namespace.object'
			));
		};

		// Custom Providers
		$providers = $object->getProperty('coid://phpmae.cloudobjects.io/usesProvider');
		if (!$providers) return;
		if (!is_array($providers)) $providers = array($providers);
		foreach ($providers as $provider) {
			$providerObject = $objectRetriever->get($provider->getId());
			if (!$providerObject) continue;
			$providerClass = $classRepository->createInstance($providerObject, $objectRetriever);
			if ($providerClass && in_array('Silex\ServiceProviderInterface', class_implements($providerClass))) {
				// Valid service providers are registered
				$app->register($providerClass);
			}
		}
	}

  	/**
	 * Configure the Silex application according to the request and mount the controller.
	 */
	public static function configure(Application $app, Request $request, array $config) {
		if (isset($config['debug']) && $config['debug'] == true) {
			ini_set("display_errors", 1);
			error_reporting(E_ALL & ~E_NOTICE);
			$app['debug'] = true;
		} else {
			ini_set('display_errors', false);
			$app['debug'] = false;
		}

		$errorHandler = new ErrorHandler;
		register_shutdown_function(function($handler) {
			$handler->getErrorResponse();
		}, $errorHandler); // see: http://stackoverflow.com/questions/4410632/handle-fatal-errors-in-php-using-register-shutdown-function        

		$path = explode('/', $request->getPathInfo());

		// Initialize CloudObjects SDK
		$objectRetriever = new ObjectRetriever(array(
			'cache_provider' => $config['object_cache'],
			'cache_provider.file.directory' => '/tmp/cache',
			'cache_provider.redis.host' => $config['redis']['host'],
			'cache_provider.redis.port' => $config['redis']['port'],
			'static_config_path' => __DIR__.'/../../../static-objects',
			'auth_ns' => $config['cloudobjects.auth_ns'],
			'auth_secret' => $config['cloudobjects.auth_secret']
		));
		$app['phpmae.identity'] = $config['cloudobjects.auth_ns'];

		// Initialize Class Repository
		$classRepository = new ClassRepository($config['classes']);

		// Check for virtual host-style namespace configuration
		if (isset($config['enable_vhost_controllers']) && $config['enable_vhost_controllers']==true
				&& $request->getHost() && !in_array($request->getHost(), $config['exclude_vhosts'])
				&& filter_var($request->getHost(), FILTER_VALIDATE_IP)===false) {

			// Vhost mode
			$namespaceObject = $objectRetriever->get('coid://'.$request->getHost());
			if ($namespaceObject
					&& $namespaceObject->getProperty('coid://phpmae.cloudobjects.io/hasDefaultController')) {
				// Get controller
				try {
					$object = $objectRetriever
						->get($namespaceObject->getProperty('coid://phpmae.cloudobjects.io/hasDefaultController')->getId());
					if (!$object) throw new PhpMAEException("Object <".$coid."> not found.");
					$controller = $classRepository->createInstance($object, $objectRetriever, $errorHandler);
					// Mount API if valid
					if (in_array('Silex\Api\ControllerProviderInterface', class_implements($controller))) {
						$app['self.object'] = $object;

						self::prepareProvidersAndTemplates($app, $object, $objectRetriever, $classRepository);
						self::prepareContext($app, $request, $config);
						$app->mount('/', $controller);
					}
				} catch (\Exception $e) {
					// remember exception for later handling
					$app['_exception_caught'] = $e;
				}
			}

		} else {
			// Regular mode (no vhosts)
			if (count($path)>=5 && $path[1]=='run') {
				// Run request for an API
				try {
					$coid = 'coid://'.$path[2].'/'.$path[3]
						.(($path[4]=='Unversioned') ? '' : '/'.$path[4]);
					$object = $objectRetriever->get($coid);
					if (!$object) throw new PhpMAEException("Object <".$coid."> not found.");

					$controller = $classRepository->createInstance($object, $objectRetriever, $errorHandler);
					// Mount API if valid
					if (in_array('Silex\Api\ControllerProviderInterface', class_implements($controller))) {
						$app['self.object'] = $object;

						self::prepareProvidersAndTemplates($app, $object, $objectRetriever, $classRepository);
						self::prepareContext($app, $request, $config);
						$app->mount('/run/'.$path[2].'/'.$path[3].'/'.$path[4], $controller);
					}
				} catch (\Exception $e) {
					// remember exception for later handling
					$app['_exception_caught'] = $e;
				}
			} else
			if (count($path)==6 && $path[1]=='uploads') {
				// Control request for uploading of configuration and/or source
				$app['object_retriever'] = $objectRetriever;
				$app['class_repository'] = $classRepository;
				$app->mount('/uploads', new UploadController());
			}
		}

		$app->after(function (Request $request, Response $response) use ($app) {
			if (isset($app['context'])) {
				$app['context']->processResponse($response);
			}
		});

		$app->error(function (\Exception $e) use ($app) {
			if (isset($app['_exception_caught'])) $e = $app['_exception_caught'];
			
			if ($app['debug'] == true) {
				// Return all exceptions in debug mode
				$response = new Response($e->getMessage());				
			} elseif (is_a($e, 'Symfony\Component\HttpKernel\Exception\HttpException')
					|| is_a($e, 'CloudObjects\PhpMAE\Exceptions\PhpMAEException')) {
				// Return HTTP or PhpMAEExceptions
				$response = new Response($e->getMessage());
			} else {
				// Return generic error message
				$response = new Response("An exception was caught while processing the request.");
			}
			$response->headers->set('Content-Type', 'text/plain');
			return $response;
		});
	}

}
