<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Symfony\Component\HttpFoundation\Request, Symfony\Component\HttpFoundation\Response;
use Silex\Application, Silex\Api\ControllerProviderInterface;

class UploadController implements ControllerProviderInterface {

	public function connect(Application $app) {
		$controllers = $app['controllers_factory'];
		$controllers->put('/{namespace}/{name}/{version}/source.php', function($namespace, $name, $version, Request $request) use ($app) {
			$object = $app['object_retriever']->get('coid://'.$namespace.'/'.$name
				.(($version=='Unversioned') ? '' : '/'.$version));;
			if (!$object) {
				return new Response('', 404);
			} // TODO: should check for local configuration as well

			$content = $request->getContent();
			$validator = new ClassValidator;

			try {
				if (TypeChecker::isController($object)) {
					// Validate as controller
					$validator->validateAsController($content);
				} else
				if (TypeChecker::isProvider($object)) {
					// Validate as provider
					$validator->validateAsProvider($content);
				} else {
					throw new \Exception('Invalid object type.');
				}
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
