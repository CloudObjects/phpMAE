<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Pimple\Container;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request, Symfony\Component\HttpFoundation\Response,
	Symfony\Component\HttpFoundation\ParameterBag;
use ML\IRI\IRI, ML\JsonLD\Node;
use GuzzleHttp\Client;
use CloudObjects\SDK\AccountGateway\AccountContext, CloudObjects\SDK\ObjectRetriever,
	CloudObjects\SDK\COIDParser, CloudObjects\SDK\NodeReader, CloudObjects\SDK\Helpers\SharedSecretAuthentication;
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
			if (isset($config['accountgateways.base_url_template']) && !empty($config['accountgateways.base_url_template'])) {
				// Apply custom configuration for Account Gateway URL
				$context->setAccountGatewayBaseURLTemplate($config['accountgateways.base_url_template']);
			}
			
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
	private static function prepareProvidersAndTemplates(Application $app, Request $request,
			Node $object, ObjectRetriever $objectRetriever, ClassRepository $classRepository,
			ErrorHandler $errorHandler) {

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
		$objectRetrieverPool = new ObjectRetrieverPool($objectRetriever, $app['phpmae.identity']);
		$app['cloudobjects'] = $objectRetrieverPool->getObjectRetriever($objectIri->getHost());

		$app['config'] = function() use ($app) {
			return new ConfigHelper($app, array(
				'self.object', 'namespace.object',
				'accessor.object', 'accessor.namespace.object'
			));
		};

		// Custom Dependencies
		DependencyInjector::processDependencies($object, $app, $objectRetriever);

		// Custom Providers
		$providers = $object->getProperty('coid://phpmae.cloudobjects.io/usesProvider');
		if (!$providers) return;
		if (!is_array($providers)) $providers = array($providers);
		foreach ($providers as $provider) {
			$providerCoid = new IRI($provider->getId());
			$providerObject = $objectRetriever->getObject($providerCoid);
			if (!$providerObject) continue;
			$providerClass = $classRepository->createInstance($providerObject, $objectRetriever, $errorHandler);
			if ($providerClass && in_array('Pimple\ServiceProviderInterface', class_implements($providerClass))) {
				// Valid service providers are registered in a scoped container,
				// then made available to the controller
				$scopedContainer = new Container;
				$scopedContainer->register($providerClass);
				foreach ($scopedContainer->keys() as $k) {
					$app[$k] = function() use ($scopedContainer, $k) {
						return $scopedContainer[$k];
					};
				}
				
				// Create scoped Object Retriever
				$hostname = $providerCoid->getHost();
				$scopedContainer['cloudobjects'] = function() use ($hostname, $objectRetrieverPool) {
					return $objectRetrieverPool->getObjectRetriever($hostname);
				};

				// Self (= provider) object
				$scopedContainer['self.object'] = function() use ($scopedContainer, $providerCoid) {
					return $scopedContainer['cloudobjects']->getObject($providerCoid);
				};
				$scopedContainer['self.namespace.object'] = function() use ($scopedContainer, $providerCoid) {
					return $scopedContainer['cloudobjects']->getObject(COIDParser::getNamespaceCOID($providerCoid));
				};

				// Controller object
				$scopedContainer['controller.object'] = function() use ($scopedContainer, $objectIri) {
					return $scopedContainer['cloudobjects']->getObject($objectIri);
				};
				$scopedContainer['controller.namespace.object'] = function() use ($scopedContainer, $objectIri) {
					return $scopedContainer['cloudobjects']->getObject(COIDParser::getNamespaceCOID($objectIri));
				};

				if ($request->headers->has('C-AAUID')
						&& $request->headers->has('C-Access-Token')) {
					
					// Context and Account
					$scopedContainer['context'] = function() use ($app) {
						return $app['context'];
					};
					$scopedContainer['account'] = function() use ($app) {
						return $app['account'];
					};
				}

				if ($request->headers->has('C-Accessor')) {
					$accessorCoid = new IRI($request->headers->get('C-Accessor'));

					// Accessor object
					$scopedContainer['accessor.object'] = function() use ($scopedContainer, $accessorCoid) {
						return $scopedContainer['cloudobjects']->getObject($accessorCoid);
					};

					// Accessor namespace object
					$scopedContainer['accessor.namespace.object'] = function() use ($scopedContainer, $accessorCoid) {
						return $scopedContainer['cloudobjects']->getObject(COIDParser::getNamespaceCOID($accessorCoid));
					};
				}

				// Config Helper
				$scopedContainer['config'] = function() use ($scopedContainer) {
					return new ConfigHelper($scopedContainer, [
						'self.object', 'namespace.object', 'controller.object', 'controller.namespace.object',
						'accessor.object', 'accessor.namespace.object'
					]);
				};
			}
		}
	}

  	/**
	 * Configure the Silex application according to the request and mount the controller.
	 */
	public static function configure(Application $app, Request $request, array $config) {
		if (isset($config['debug']) && $config['debug'] === true) {
			ini_set("display_errors", 1);
			error_reporting(E_ALL & ~E_NOTICE);
			$app['debug'] = true;
		} else {
			ini_set('display_errors', false);
			$app['debug'] = false;
		}

		// Copy config variables
		$app['client_authentication'] = @$config['client_authentication'];

		$errorHandler = new ErrorHandler;
		register_shutdown_function(function(ErrorHandler $handler) {
			$handler->getErrorResponse();
		}, $errorHandler); // see: http://stackoverflow.com/questions/4410632/handle-fatal-errors-in-php-using-register-shutdown-function        

		$path = explode('/', $request->getPathInfo());

		// Initialize Reader
		$reader = new NodeReader([
			'prefixes' => [
				'phpmae' => 'coid://phpmae.cloudobjects.io/'
			]
		]);

		// Initialize CloudObjects SDK
		$objectRetriever = new ObjectRetriever([
			'cache_provider' => $config['object_cache'],
			'cache_provider.file.directory' => '/tmp/cache',
			'cache_provider.redis.host' => @$config['redis']['host'],
			'cache_provider.redis.port' => @$config['redis']['port'],
			'static_config_path' => __DIR__.'/../../../static-objects',
			'auth_ns' => @$config['cloudobjects.auth_ns'],
			'auth_secret' => @$config['cloudobjects.auth_secret']
		]);
		$app['phpmae.identity'] = @$config['cloudobjects.auth_ns'];

		if (!isset($config['cloudobjects.auth_ns']) && CredentialManager::isConfigured()) {
			// Access Object API via Account Gateway using local developer account
			$objectRetriever->setClient(CredentialManager::getAccountContext()->getClient(), '/ws/');
			$app['debug'] = true;
		}

		// Initialize Class Repository
		$classRepository = new ClassRepository($config['classes']);

		// Check for virtual host-style namespace configuration
		if (isset($config['enable_vhost_controllers']) && $config['enable_vhost_controllers'] === true
				&& $request->getHost() && !in_array($request->getHost(), $config['exclude_vhosts'])
				&& filter_var($request->getHost(), FILTER_VALIDATE_IP)===false) {

			// Vhost mode
			$namespaceObject = $objectRetriever->get('coid://'.$request->getHost());
			if ($namespaceObject
					&& $reader->hasProperty($namespaceObject, 'phpmae:hasDefaultController')) {

				// Get default controller
				try {
					$object = $objectRetriever->getObject(
						$reader->getFirstValueIRI($namespaceObject, 'phpmae:hasDefaultController'));
					if (!$object) throw new PhpMAEException("Object <".$coid."> not found.");
					$controller = $classRepository->createInstance($object, $objectRetriever, $errorHandler);
					// Mount API if valid
					if (ClassValidator::isController($controller)) {
						$app['self.object'] = $object;

						self::prepareProvidersAndTemplates($app, $request, $object,
							$objectRetriever, $classRepository, $errorHandler);
						self::prepareContext($app, $request, $config);
						$app->mount('/', $controller);
					}
				} catch (\Exception $e) {
					// remember exception for later handling
					$app['_exception_caught'] = $e;
				}
			} elseif ($namespaceObject
					&& $reader->hasProperty($namespaceObject, 'phpmae:hasMountPoint')) {
				// Find mounted controllers
				try {
					$mps = $reader->getAllValuesNode($namespaceObject, 'phpmae:hasMountPoint');
					foreach ($mps as $mp) {
						if ($reader->getFirstValueString($mp, 'phpmae:mountsOnPath')
								== $path[1]) {
							$object = $objectRetriever->getObject(
								$reader->getFirstValueIRI($mp, 'phpmae:mountsController'));
							if (!$object) throw new PhpMAEException("Object <".$coid."> not found.");
							$controller = $classRepository->createInstance($object,
								$objectRetriever, $errorHandler);
							// Mount API if valid
							if (ClassValidator::isController($controller)) {
								$app['self.object'] = $object;

								self::prepareProvidersAndTemplates($app, $request,
									$object, $objectRetriever, $classRepository, $errorHandler);
								self::prepareContext($app, $request, $config);
								$app->mount('/'.$path[1], $controller);
							}
						}
					}
				} catch (\Exception $e) {
					// remember exception for later handling
					$app['_exception_caught'] = $e;
				}
			}

		} else {
			// Regular mode (no vhosts)

			$app->get('/', function() {
				return file_get_contents(__DIR__."/../web/static-home.html");
			});

			if (count($path)>=5 && $path[1]=='run') {
				// Run request for an API
				try {
					$coid = 'coid://'.$path[2].'/'.$path[3]
						.(($path[4]=='Unversioned') ? '' : '/'.$path[4]);
					$object = $objectRetriever->get($coid);
					if (!$object) throw new PhpMAEException("Object <".$coid."> not found.");

					$runclass = $classRepository->createInstance($object, $objectRetriever, $errorHandler);
					if (ClassValidator::isController($runclass)) {
						// Mount if valid controller
						$app['self.object'] = $object;

						self::prepareProvidersAndTemplates($app, $request, $object,
							$objectRetriever, $classRepository, $errorHandler);
						self::prepareContext($app, $request, $config);
						$app->mount('/run/'.$path[2].'/'.$path[3].'/'.$path[4], $runclass);
					} else
					if (ClassValidator::isFunction($runclass)) {
						// Create single route for function
						$app->post('/run/'.$path[2].'/'.$path[3].'/'.$path[4].'/',
							function(Request $r) use ($runclass, $app) {
								// Prepare input
								if ($r->request->count() <= 1 && $r->getContent()[0] == "{")
									$parameters = new ParameterBag(json_decode($r->getContent(), true));
								else
									$parameters = $r->request;
								
								// Run function
								$result = $runclass->execute($parameters, $app);

								// Generate response
								if (!isset($result))
									return new Response("", 204);
								elseif (is_string($result))
									return new Response($result, 200, [ 'Content-Type' => 'text/plain' ]);
								elseif (is_a($result, 'Symfony\Component\HttpFoundation\Response'))
									return $result;
								else
									return $app->json($result);
							});
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

		$app->before(function(Request $r) use ($app) {
			switch ($app['client_authentication']) {
				case "shared_secret.controller":
					// Require shared secret client authentication by the namespace of the controller
					if (isset($app['self.object'])
							&& SharedSecretAuthentication::verifyCredentials($app['cloudobjects'], $r->getUser(), $r->getPassword())
								!= SharedSecretAuthentication::RESULT_OK
							&& 'coid://'.$r->getUser() != $app['self.object']->getId()) {
						// Ask for authentication
						return new Response('', 401, [
							'WWW-Authenticate' => 'Basic realm="phpMAE"'
						]);
					}					
			}
			foreach ($app['routes']->all() as $route) {
				$controller = $route->getDefaults()['_controller'];
				if (is_string($controller) && substr($controller, 0, 27) != "\CloudObjects\PhpMAE\Class_") {
					$app->abort(500, "Controller is trying to break out of sandbox!");
				}
			}
		});

		$app->after(function (Request $request, Response $response) use ($app) {
			if (isset($app['context'])) {
				$app['context']->processResponse($response);
			}
		});

		$app->error(function (\Exception $e) use ($app) {
			if (isset($app['_exception_caught'])) $e = $app['_exception_caught'];
			
			if ($app['debug'] === true) {
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
