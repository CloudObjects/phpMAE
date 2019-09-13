<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use ML\JsonLD\Node, ML\JsonLD\Graph;
use GuzzleHttp\Psr7\ServerRequest;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class InteractiveRunController {

	private $classRepository;
	private $session;

	public function __construct(ClassRepository $classRepository,
			ContainerInterface $container) {

		$this->classRepository = $classRepository;
		$this->container = $container;
	}

	private function createTemporaryClassObject() {
		$graph = new Graph;
		$type = new Node($graph, 'coid://phpmae.cloudobjects.io/Class');
		
		$object = new Node($graph, 'coid://'.$this->session.'.phpmae/MyPhpMAEClass');
		$object->setType($type);

		return $object;
	}

	public function handle(RequestInterface $request, Engine $engine) {
		if (!$this->container->has('interactive_run')
				|| $this->container->get('interactive_run') !== true)
			throw new PhpMAEException("This is not an environment with interactive run enabled.");

		if ($request->getMethod() != 'POST')
			throw new PhpMAEException("Must use POST for run requests.");

		$runData = $request->getParsedBody();

		$this->session = isset($runData['session'])
			? $runData['session']
			: uniqid();

		try {
			$object = $this->createTemporaryClassObject();
			$engine->setRunClass($this->classRepository
				->createInstance($object, $request, [], $runData['sourceCode'])
			);
		} catch (\Exception $e)	 {
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
	}

}
