<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Psr\Http\Message\RequestInterface, Psr\Http\Message\ResponseInterface,
    Psr\Http\Message\ServerRequestInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Http\Headers, Slim\Http\Request, Slim\Http\Response, Slim\Http\Environment;
use JsonRpc\Server as JsonRPC;
use ML\IRI\IRI;
use ML\JsonLD\Node;
use Tuupola\Middleware\HttpBasicAuthentication;
use CloudObjects\SDK\COIDParser, CloudObjects\SDK\NodeReader, CloudObjects\SDK\ObjectRetriever;
use CloudObjects\SDK\Helpers\SharedSecretAuthentication;
use CloudObjects\PhpMAE\DI\SandboxedContainer;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class Engine implements RequestHandlerInterface {

    const SKEY = '__self';

    private $objectRetriever;
    private $classRepository;
    private $errorHandler;
    private $slim;
    private $container;

    private $object;
    private $runClass;

    public function __construct(ObjectRetriever $objectRetriever,
            ClassRepository $classRepository, ErrorHandler $errorHandler,
            App $slim, ContainerInterface $container) {
        
        $this->objectRetriever = $objectRetriever;
        $this->classRepository = $classRepository;
        $this->errorHandler = $errorHandler;
        $this->slim = $slim;
        $this->container = $container;
    }

    private function getAuthenticationMiddleware() {
        $auth = $this->container->get('client_authentication');
        switch ($auth) {
            case "shared_secret.runclass":
            case "shared_secret.runclass+secure":
                $object = $this->object;
                return new HttpBasicAuthentication([
                    'secure' => ($auth == 'shared_secret.runclass+secure'),
                    'realm' => 'phpMAE',
                    'authenticator' => function($args) use ($object) {
                        return SharedSecretAuthentication::verifyCredentials($this->objectRetriever,
                            $args['user'], $args['password']) == SharedSecretAuthentication::RESULT_OK
                            && strpos($object->getId(), '/'.$args['user'].'/') !== false;
                    }
                ]);
            case "none":
                return new EmptyMiddleware;
            default:
                throw new PhpMAEException("Unsupported authentication mode!");
        }
    }

    /**
     * Executes an invokable class.
     */
    private function executeInvokableClass(SandboxedContainer $runClass, RequestInterface $request) {
        $input = $request->getParsedBody();
        if (!is_array($input))
            $input = [];

        $result = $runClass->get(self::SKEY)->__invoke($input);
                
        if (!isset($result))
            // Empty response
            return new Response(204);
        elseif (is_string($result) && (substr($result, 0, 5) == '<html' || substr($result, 0, 14) == '<!doctype html'))
            // HTML response
            return (new Response)->write($result);
        elseif (is_string($result) || is_numeric($result))
            // Plain text response
            return (new Response)->withHeader('Content-Type', 'text/plain')->write($result);
        elseif (is_object($result) && in_array(ResponseInterface::class, class_implements($result)))
            // Existing response
            return $result;
        else
            // JSON response (default)
            return (new Response)->withJson($result);
        
        // TODO: add support for XML
    }

    /**
     * Executes a standard class using JSON-RPC.
     */
    private function executeJsonRPC(SandboxedContainer $runClass, RequestInterface $request) {        
        $transport = new JsonRPCTransport;
        $server = new JsonRPC($runClass->get(self::SKEY), $transport);
        $server->receive((string)$request->getBody());
        return $transport->getResponse();
    }

    /**
     * Start execution of a request.
     */
    public function execute(RequestInterface $request) {
        $path = rtrim($request->getUri()->getBasePath().$request->getUri()->getPath(), '/');

        if ($path == '/uploadTestenv') {
            return $this->container
                ->get(UploadController::class)
                ->handle($request);
        }

        $coid = COIDParser::fromString(substr($path, 1));
        $this->loadRunClass($coid);
        
        $auth = $this->getAuthenticationMiddleware($object);
        return $auth->process($request, $this);
    }

    /**
     * Load a class to execute.
     */
    public function loadRunClass(IRI $coid) {
        if (COIDParser::isValidCOID($coid) && COIDParser::getType($coid) != COIDParser::COID_ROOT) {
            $this->object = $this->objectRetriever->get($coid);
            if (!isset($this->object))
                throw new PhpMAEException("The object <" . (string)$coid . "> does not exist or this phpMAE instance is not allowed to access it.");
            $this->runClass = $this->classRepository->createInstance($this->object, $this->objectRetriever, $this->errorHandler);
        } else {
            throw new PhpMAEException("You must provide a valid, non-root COID to specify the class for execution.");
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface {
        if (ClassValidator::isInvokableClass($this->runClass->get(self::SKEY))) {
            // Run as invokable class
            return $this->executeInvokableClass($this->runClass, $request);
        } else {
            // Run as RPC
            // JsonRPC
            return $this->executeJsonRPC($this->runClass, $request);
        }
    }

    /**
     * Create main request and execute.
     */
    public function run() {
        $env = new Environment($_SERVER);
        $request = Request::createFromEnvironment($env);
    
        try {
            $response = $this->execute($request);
        } catch (PhpMAEException $e) {
            // Create plain-text error response
            $response = (new Response(500))
                ->withHeader('Content-Type', 'text/plain')
                ->write($e->getMessage());
        }
    
        $this->slim->respond($response);        
    }
}