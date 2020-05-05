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
use ML\IRI\IRI;
use ML\JsonLD\Node;
use Tuupola\Middleware\HttpBasicAuthentication;
use Dflydev\FigCookies\SetCookie, Dflydev\FigCookies\FigResponseCookies;
use CloudObjects\SDK\COIDParser, CloudObjects\SDK\NodeReader,
    CloudObjects\SDK\ObjectRetriever;
use CloudObjects\SDK\Helpers\SharedSecretAuthentication;
use CloudObjects\PhpMAE\DI\SandboxedContainer;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class Engine implements RequestHandlerInterface {

    const SKEY = '__self';

    const CO_PUBLIC = 'coid://cloudobjects.io/Public';

    private $objectRetriever;
    private $classRepository;
    private $slim;
    private $container;

    private $object;
    private $runClass;

    public function __construct(ObjectRetriever $objectRetriever,
            ClassRepository $classRepository, App $slim,
            ErrorHandler $errorHandler, ContainerInterface $container) {
        
        $this->objectRetriever = $objectRetriever;
        $this->classRepository = $classRepository;
        $this->slim = $slim;
        $this->container = $container;

        register_shutdown_function(function(ErrorHandler $handler) {
            $handler->getErrorResponse();
		}, $errorHandler); // see: http://stackoverflow.com/questions/4410632/handle-fatal-errors-in-php-using-register-shutdown-function
    }

    private function isObjectPublic() {
        $reader = new NodeReader([ 'prefixes' => [ 'co' => 'coid://cloudobjects.io/' ]]);
        return ($reader->hasProperty($this->object, 'co:isVisibleTo')
            && $reader->getFirstValueIRI($this->object, 'co:isVisibleTo')
                ->equals(self::CO_PUBLIC)
            && $reader->hasProperty($this->object, 'co:permitsUsageTo')
            && $reader->getFirstValueIRI($this->object, 'co:permitsUsageTo')
                ->equals(self::CO_PUBLIC));
    }

    private function getAuthenticationMiddleware() {
        $authSchemes = explode('|', $this->container->get('client_authentication'));
        if (in_array('none', $authSchemes))
            return new EmptyMiddleware; // no authentication required

        if (in_array('none:public_only', $authSchemes)
                && $this->isObjectPublic())
            return new EmptyMiddleware; // no authentication required

        $object = $this->object;
        return new HttpBasicAuthentication([
            'secure' => $this->container->get('client_authentication_must_be_secure'),
            'realm' => 'phpMAE',
            'authenticator' => function($args) use ($object, $authSchemes) {
                $authenticated = false;
                foreach ($authSchemes as $as) {
                    if (substr($as, 0, 14) == 'shared_secret:') {
                        $authResult = (SharedSecretAuthentication::verifyCredentials(
                            $this->objectRetriever, $args['user'], $args['password'])
                            == SharedSecretAuthentication::RESULT_OK);
                        if ($authResult != true)
                            continue;
                        
                        if (substr($as, 14) == 'runclass')
                            $authenticated = (substr($object->getId(), 7, strlen($args['user']) +1) == $args['user'].'/');
                        else
                            $authenticated = (substr($as, 14) == 'coid://'.$args['user']);
                    } elseif (substr($as, 0, 4) != 'none')
                        throw new PhpMAEException("Unsupported authentication scheme!");
                    
                    if ($authenticated == true)
                        break;
                }

                return $authenticated;
            }
        ]);
    }

    /**
     * Executes an invokable class.
     */
    private function executeInvokableClass(RequestInterface $request, $args = null) {
        if (is_array($args) && count($args) > 0) {
            $input = $args;
        } else {
            $input = $request->getParsedBody();
            if (!is_array($input))
                $input = [];    
        }

        $result = $this->runClass->get(self::SKEY)->__invoke($input);

        return $this->generateResponse($result);
    }

    /**
     * Generates an response with adequate Content Type based on the format of the content.
     * @param mixed $content Content for the body of the response.
     */
    public function generateResponse($content) {
        $lowercaseContent = is_string($content) ? strtolower($content) : '';
        if (!isset($content))
            // Empty response
            $response = new Response(204);
        elseif (is_string($content) && (substr($lowercaseContent, 0, 5) == '<html' || substr($lowercaseContent, 0, 14) == '<!doctype html'))
            // HTML response
            $response = (new Response)->write($content);
        elseif (is_string($content) && (substr($lowercaseContent, 0, 7) == 'http://' || substr($lowercaseContent, 0, 8) == 'https://'))
            // Redirect response
            $response = (new Response)->withRedirect($content);
        elseif (is_string($content) || is_numeric($content))
            // Plain text response
            $response = (new Response)->withHeader('Content-Type', 'text/plain')->write($content);
        elseif (is_object($content) && in_array(ResponseInterface::class, class_implements($content)))
            // Existing response to pass through
            $response = $content;
        else
            // JSON response (default)
            $response = (new Response)->withJson($content);
    
        // TODO: add support for XML

        // Add cookies if any
        if (isset($this->runClass) && $this->runClass->has('cookies')) {
            foreach ($this->runClass->get('cookies') as $cookie) {
                if (is_a($cookie, SetCookie::class))
                    $response = FigResponseCookies::set($response, $cookie);
            }
        }
        
        return $response;
    }

    /**
     * Executes a standard class using JSON-RPC.
     */
    private function executeJsonRPC(RequestInterface $request) {
        $transport = new JsonRPCTransport;
        $server = new JsonRPCServer($this->runClass->get(self::SKEY), $transport);
        $server->receive((string)$request->getBody());
        return $this->generateResponse($transport->getResponse());
    }

    /**
     * Start execution of a request.
     */
    public function execute(RequestInterface $request) {
        $path = rtrim($request->getUri()->getBasePath().$request->getUri()->getPath(), '/');
        switch ($path) {
            case "":
            case "/":
                // Display homepage
                $file = ($this->container
                    ->get(InteractiveRunController::class)
                    ->isEnabled()) ? 'app.html' : 'app_disabled.html';
                
                return $this->generateResponse(file_get_contents(__DIR__.'/../static/'.$file));
            case "/run":
                // Run interactive code request
                return $this->container
                    ->get(InteractiveRunController::class)
                    ->handle($request, $this);
            case "/uploadTestenv":
                // Upload into test environment (if enabled)
                return $this->container
                    ->get(UploadController::class)
                    ->handle($request);
            default:
                if (file_exists(__DIR__.'/../static'.$path)) {
                    // Proxy for static files
                    $filename = realpath(__DIR__.'/../static'.$path);
                    $response = (new Response)->write(file_get_contents($filename));
                    switch (pathinfo($filename, PATHINFO_EXTENSION)) {
                        case "css":
                            return $response->withHeader('Content-Type', 'text/css');
                        case "js":
                            return $response->withHeader('Content-Type', 'application/javascript');
                        default:
                            return $response;
                    }
                    
                }

                $coid = COIDParser::fromString(substr($path, 1));
                $this->loadRunClass($coid, $request);
        
                // Process a standard request for a phpMAE class
                return $this->getAuthenticationMiddleware()
                    ->process($request, $this);
        }
    }

    /**
     * Load a class to execute.
     */
    public function loadRunClass(IRI $coid, RequestInterface $request = null) {
        if (COIDParser::isValidCOID($coid) && COIDParser::getType($coid) != COIDParser::COID_ROOT) {
            $this->object = $this->objectRetriever->get($coid);
            if (!isset($this->object))
                throw new PhpMAEException("The object <" . (string)$coid . "> does not exist or this phpMAE instance is not allowed to access it.");
            $this->runClass = $this->classRepository->createInstance($this->object, $request);
        } else {
            throw new PhpMAEException("You must provide a valid, non-root COID to specify the class for execution.");
        }
    }

    /**
     * Specifies the class for execution in the engine.
     */
    public function setRunClass(DI\SandboxedContainer $runClass) {
        $this->runClass = $runClass;
    }

    public function handle(ServerRequestInterface $request, $args = null): ResponseInterface {
        try {
            if (ClassValidator::isInvokableClass($this->runClass->get(self::SKEY))) {
                // Run as invokable class
                return $this->executeInvokableClass($request, $args);
            } else {
                // Run as RPC
                // JsonRPC
                return $this->executeJsonRPC($request);
            }
        } catch (\Exception $e) {
            throw new PhpMAEException(get_class($e).": ".$e->getMessage());
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