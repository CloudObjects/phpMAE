<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Exception;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use ML\JsonLD\JsonLD;
use CloudObjects\SDK\COIDParser;
use CloudObjects\SDK\AccountGateway\AccountContext, CloudObjects\SDK\AccountGateway\AAUIDParser;
use CloudObjects\Utilities\RDF\Arc2JsonLdConverter;
use GuzzleHttp\Psr7\ServerRequest;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class InteractiveRunController {

	private $classRepository;
	private $session;
	private $mapBack = [];
	private $accountContext;
	private $domains;

	public function __construct(ClassRepository $classRepository,
			ContainerInterface $container) {

		$this->classRepository = $classRepository;
		$this->container = $container;		
	}

	public function isEnabled() {
		return ($this->container->has('interactive_run')
			&& $this->container->get('interactive_run') === true);
	}

	public function getOriginalHostname($sessionHostname) {
		if (!isset($this->accountContext)) {
			// We cannot access original hostnames for anonymous users
			return null;
		}

		if (!isset($this->domains)) {
			// Get domains that the user has access to
			$apiResponse = json_decode($this->accountContext->getClient()->get('dr/')
				->getBody(), true);
			$this->domains = isset($apiResponse['domains']) ? $apiResponse['domains'] : [];
		}
		
		$hostname = @$this->mapBack[$sessionHostname];
		return (isset($hostname) && in_array($hostname, $this->domains))
			? $hostname : null;
	}

	private function createTemporaryClassObject($className, $config) {
		// Parse RDF/XML config
		$parser = \ARC2::getRDFXMLParser();
		$parser->parse('', $config);
		$index = $parser->getSimpleIndex(false);

		$rewrittenIndex = [];
		$targetName = '';
		foreach ($index as $subject => $data) {
			$coid = COIDParser::fromString($subject);
			if (COIDParser::getName($coid) == $className) {
				// Rewrite namespace to not interfere with production classes
				$targetName = 'coid://'.$this->session.'.phpmae'.$coid->getPath();
				$this->mapBack[$this->session.'.phpmae'] = $coid->getHost();
				$rewrittenIndex[$targetName] = $data;
			} else
				$rewrittenIndex[$subject] = $data;
		}

		if (empty($targetName))
			throw new Exception("Could not find configuration for <".$className.">.");

		// Convert to JsonLD as required by the CloudObjects SDK
		$document = JsonLD::getDocument(
			JsonLD::fromRdf(Arc2JsonLdConverter::indexToQuads($rewrittenIndex))
		);

		// Find object
		$node = $document->getGraph()->getNode($targetName);

		if (!isset($node))
			throw new Exception("Invalid object configuration.");

		return $node;		
	}

	public function handle(RequestInterface $request, Engine $engine) {
		if (!$this->isEnabled())
			throw new PhpMAEException("This is not an environment with interactive run enabled.");

		if ($request->getMethod() != 'POST')
			throw new PhpMAEException("Must use POST for run requests.");

		$runData = $request->getParsedBody();

		$this->session = isset($runData['session'])
			? $runData['session']
			: uniqid();

		if (isset($runData['aauid']) && isset($runData['access_token'])) {
			// We have user credentials
			$this->accountContext = new AccountContext(
				AAUIDParser::fromString($runData['aauid']), $runData['access_token']
			);
		}

		try {
			$object = $this->createTemporaryClassObject($runData['class'], $runData['config']);
			$engine->setRunClass($this->classRepository
				->createInstance($object, $request, [], $runData['sourceCode'])
			);
		} catch (Exception $e)	 {
			// Catch validation errors
			return $engine->generateResponse([
				'status' => 'error',
				'content' => $e->getMessage(),
				'session' => $this->session
			]);
		}

		$rpcBody = json_encode([
			'jsonrpc' => '2.0',
			'method' => $runData['method'],
			'params' => $runData['params'],
			'id' => 'PG'
		]);

		// Update error handler for response format required by client
		$this->container->get(ErrorHandler::class)
			->setResponseGenerator(function($error, $object) use ($engine) {
				$message = substr($error['message'], 0,  strpos($error['message'], ' in /'));
				return $engine->generateResponse([
					'status' => 'error',
					'content' => $message,
					'session' => $this->session
				]);
			});

		try {
			$innerRequest = new ServerRequest('POST', $request->getUri(),
				$request->getHeaders(), $rpcBody);
			$innerResponse = $engine->handle($innerRequest);
			$rpcResponse = json_decode($innerResponse->getBody(), true);

        	if (isset($rpcResponse['result']))
            	return $engine->generateResponse([
					'status' => 'success',
					'content' => $rpcResponse['result'],
					'session' => $this->session
				]);
			else
				return $engine->generateResponse([
					'status' => 'error',
					'content' => $rpcResponse['error']['message'],
					'session' => $this->session
				]);
		} catch (PhpMAEException $e) {
			return $engine->generateResponse([
				'status' => 'error',
				'content' => $e->getMessage(),
				'session' => $this->session
			]);
		}
	}

}
