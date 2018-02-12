<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Symfony\Component\HttpFoundation\Request, Symfony\Component\HttpFoundation\Response;
use Silex\Application, Silex\Api\ControllerProviderInterface;
use ML\JsonLD\JsonLD;
use CloudObjects\Utilities\RDF\Arc2JsonLdConverter;

class UploadController implements ControllerProviderInterface {

	private $config;

	public function __construct(array $config) {
		$this->config = $config;
	}

	public function connect(Application $app) {
		$controllers = $app['controllers_factory'];
		$config = $this->config;

		$controllers->put('/{namespace}/{name}/{version}/config.xml',
			function($namespace, $name, $version, Request $request) use ($app, $config) {
				if (@$config['uploads'] != true)
					$app->abort(403);

				// Parse RDF/XML config
				$parser = \ARC2::getRDFXMLParser();
				$parser->parse('', $request->getContent());
				$index = $parser->getSimpleIndex(false);

				// Convert to JsonLD as required by the CloudObjects SDK
				$jsonLdConfig = JsonLD::compact(
					JsonLD::fromRdf(Arc2JsonLdConverter::indexToQuads($index)));

				// Validate config
				if (!isset($jsonLdConfig->{'@id'})
					|| $jsonLdConfig->{'@id'} != 'coid://'.$namespace.'/'.$name.
						($version == 'Unversioned' ? '' : '/'.$version)) {
					return $app->abort(400, "Uploaded configuration does not correspond to object.");
				}

				// Store configuration
				$path = $config['uploads_dir']
					.DIRECTORY_SEPARATOR.'config'
					.DIRECTORY_SEPARATOR.$namespace
					.DIRECTORY_SEPARATOR.$name
					.($version == 'Unversioned' ? '' : DIRECTORY_SEPARATOR.$version);
				if (!is_dir($path)) mkdir($path, 0777, true);

				file_put_contents($path.DIRECTORY_SEPARATOR.'object.jsonld', json_encode($jsonLdConfig));

				return new Response('', 201);
			});

		$controllers->put('/{namespace}/{name}/{version}/source.php', function($namespace, $name, $version, Request $request) use ($app, $config) {						
			if (!(@$config['uploads'] == true || Sandbox::isAuthenticated($request, $namespace, $config)))
				$app->abort(403);

			$object = $app['object_retriever']->get('coid://'.$namespace.'/'.$name
				.(($version=='Unversioned') ? '' : '/'.$version));;
			if (!$object)
				$app->abort(403);

			$content = $request->getContent();
			$validator = new ClassValidator;

			try {
				if (TypeChecker::isController($object))
					// Validate as controller
					$validator->validateAsController($content);
				elseif (TypeChecker::isFunction($object))
					// Validate as function
					$validator->validateAsFunction($content);
				elseif (TypeChecker::isProvider($object))
					// Validate as provider
					$validator->validateAsProvider($content);
				else
					throw new \Exception('Invalid object type.');
			} catch (\Exception $e) {
				$response = $app->json(array(
					'error_code' => get_class($e),
					'error_message' => $e->getMessage()
				));
				$response->setStatusCode(400);
				return $response;
			}

			if ($app['class_repository']->storeUploadedFile($object, $content))
				return new Response('', 201);
			else
				return new Response('', 500);
		});
		return $controllers;
	}

}
