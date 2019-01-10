<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Slim\Http\Response;
use ML\IRI\IRI;
use ML\JsonLD\JsonLD;
use CloudObjects\Utilities\RDF\Arc2JsonLdConverter;
use CloudObjects\SDK\ObjectRetriever;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class UploadController {

	private $container;
	private $objectRetriever;
	private $classRepository;

	public function __construct(ContainerInterface $container, ObjectRetriever $objectRetriever,
			ClassRepository $classRepository) {
		
		$this->container = $container;
		$this->objectRetriever = $objectRetriever;
		$this->classRepository = $classRepository;
	}	

	private function uploadSource(RequestInterface $request) {
		$object = $this->objectRetriever->get($request->getQueryParam('coid'));
		if (!$object)
			throw new PhpMAEException("Unable to retrieve object.");

		$content = (string)$request->getBody();
		$validator = new ClassValidator;

		try {
			$validator->validate($content,
				TypeChecker::getAdditionalTypes($object));
		} catch (\Exception $e) {
			$response = (new Response(400))->withJson([
				'error_code' => get_class($e),
				'error_message' => $e->getMessage()
			]);
			return $response;
		}

		if ($this->classRepository->storeUploadedFile($object, $content))
			return new Response(201);
		else
			return new Response(500);
	}

	private function uploadConfig(RequestInterface $request) {
		// Parse RDF/XML config
		$parser = \ARC2::getRDFXMLParser();
		$parser->parse('', (string)$request->getBody());
		$index = $parser->getSimpleIndex(false);

		// Convert to JsonLD as required by the CloudObjects SDK
		$jsonLdConfig = JsonLD::compact(
			JsonLD::fromRdf(Arc2JsonLdConverter::indexToQuads($index)));

		// Validate config
		if (!isset($jsonLdConfig->{'@id'})
				|| $jsonLdConfig->{'@id'} != $request->getQueryParam('coid')) {
			throw new PhpMAEException("Uploaded configuration does not correspond to object.");
		}

		// Store configuration
		$iri = new IRI($request->getQueryParam('coid'));
		$path = $this->container->get('uploads_dir')
			.DIRECTORY_SEPARATOR.'config'
			.DIRECTORY_SEPARATOR.$iri->getHost()
			.$iri->getPath();
		if (!is_dir($path)) mkdir($path, 0777, true);

		file_put_contents($path.DIRECTORY_SEPARATOR.'object.jsonld', json_encode($jsonLdConfig));

		return new Response(201);
	}

	public function handle(RequestInterface $request) {
		if (!$this->container->has('uploads') || $this->container->get('uploads') !== true)
			throw new PhpMAEException("This is not a test environment where uploads are permitted.");

		if ($request->getMethod() != 'PUT')
			throw new PhpMAEException("Must use PUT for uploading to test environment.");

		switch ($request->getQueryParam('type')) {
			case "source":
				return $this->uploadSource($request);
				break;
			case "config":
				return $this->uploadConfig($request);
				break;
			default:
				throw new PhpMAEException("Invalid test environment request.");
		}
	}

}
