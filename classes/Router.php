<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Psr\Http\Message\RequestInterface, Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Http\Environment, Slim\Http\Uri, Slim\Http\Response, Slim\Http\Request;
use GuzzleHttp\Psr7\ServerRequest;
use ML\JsonLD\Node, ML\JsonLD\JsonLD;
use CloudObjects\Utilities\RDF\Arc2JsonLdConverter;
use CloudObjects\SDK\NodeReader, CloudObjects\SDK\ObjectRetriever;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class Router {

    private $engine;
    private $objectRetriever;
    private $cotnainer;

    public function __construct(Engine $engine, ObjectRetriever $objectRetriever,
            ContainerInterface $container) {
        
        $this->engine = $engine;
        $this->objectRetriever = $objectRetriever;
        $this->container = $container;
    }

    private function configure(App $app, Node $object) {
        $reader = new NodeReader([
            'prefixes' => [
                'phpmae' => 'coid://phpmae.cloudobjects.io/',
                'wa' => 'coid://webapi.cloudobjects.io/',
                'agws' => 'coid://accountgateways.cloudobjects.io/',
            ]
        ]);

        $routes = array_merge(
            $reader->getAllValuesNode($object, 'phpmae:hasRoute'),
            $reader->getAllValuesNode($object, 'agws:hasMethod')
        );
        
        foreach ($routes as $r) {
            if (!$reader->hasProperty($r, 'wa:hasVerb') || !$reader->hasProperty($r, 'wa:hasPath'))
                throw new PhpMAEException("Incomplete route configuration! Routes must have wa:hasVerb and wa:hasPath.");

            $engine = $this->engine;
            $app->map([ $reader->getFirstValueString($r, 'wa:hasVerb') ],
                $reader->getFirstValueString($r, 'wa:hasPath'),
                function(RequestInterface $request, ResponseInterface $response, $args) use ($r, $reader, $engine) {
                    if ($reader->hasProperty($r, 'phpmae:runsClass')) {
                        // Route is mapped to a class
                        $engine->loadRunClass($reader->getFirstValueIRI($r, 'phpmae:runsClass'), $request);
                        if ($reader->hasProperty($r, 'phpmae:runsMethod')) {
                            // Calls to specific methods must be rewritten
                            // Path, request and query parameters are merged and used as method input
                            $rpcBody = json_encode([
                                'jsonrpc' => '2.0',
                                'method' => $reader->getFirstValueString($r, 'phpmae:runsMethod'),
                                'params' => (is_array($args) && count($args) > 0) ? $args
                                    : ($request->getMethod() == 'POST'
                                        ? (is_array($request->getParsedBody()) ? $request->getParsedBody() : [])
                                        : (is_array($request->getQueryParams()) ? $request->getQueryParams() : [])
                                ),
                                'id' => 'R'
                            ]);
                            $innerRequest = new ServerRequest('POST', $request->getUri(),
                                $request->getHeaders(), $rpcBody);

                            $innerResponse = $engine->handle($innerRequest);
                            $rpcResponse = json_decode($innerResponse->getBody(), true);

                            if (isset($rpcResponse['result'])) {
                                return $engine->generateResponse($rpcResponse['result']);
                            } else {
                                return (new Response(500))->withJson($rpcResponse);
                            }                            
                        } else {
                            // Generic class execution (invokable)
                            return $engine->handle($request, (is_array($args) && count($args) > 0) ? $args : null);
                        }
                    } elseif ($reader->hasProperty($r, 'phpmae:redirectsToURL')) {
                        return (new Response)->withRedirect($reader->getFirstValueString($r, 'phpmae:redirectsToURL'));
                    } else {
                        // Route has no implementation
                        return new Response(500);
                    }
                });
        }
    }

    /**
     * Setup and run router.
     */
    public function run() {
		try {
            $modes = explode('|', $this->container->get('mode'));
            $fallback = false;
    
            $configuration = [
                'settings' => [
                    'displayErrorDetails' => true,
                ],
            ];
            $c = new \Slim\Container($configuration);
            $app = new App($c);
            

            $router = null;
            $env = new Environment($_SERVER);
            foreach ($modes as $m) {
                switch ($m) {
                    case 'router:vhost':
                        if ($router == null) {
                            $uri = Uri::createFromEnvironment($env);
                            if ($uri->getHost() == 'localhost' || filter_var($uri->getHost(), FILTER_VALIDATE_IP) !== false) continue;

                            $namespace = $this->objectRetriever->get('coid://'.$uri->getHost());
                            if (isset($namespace) && $routerCoid = $namespace->getProperty('coid://phpmae.cloudobjects.io/hasRouter'))
                                $router = $this->objectRetriever->get($routerCoid->getId());
                        }
                        break;
                    case 'router:header':
                        if ($router == null) {
                            $request = Request::createFromEnvironment($env);
                            if ($request->hasHeader('C-PhpMae-Router-COID'))
                                $router = $this->objectRetriever->get($request->getHeaderLine('C-PhpMae-Router-COID'));
                        }
                        break;
                    case 'default':
                        $fallback = true;
                        break;
                    default:
                        if (substr($m, 0, 14) == 'router:coid://')
                            $router = $this->objectRetriever->get(substr($m, 7));
                        else
                            throw new PhpMAEException("Unsupported mode!");
                }
            }

            if (isset($router)) {
                $this->configure($app, $router);
                $response = $app->run(true);
            } elseif ($fallback) {
                $this->container->get(Engine::class)
                    ->run();
                exit;
            } else {
                throw new PhpMAEException("No router found!");
            }

        } catch (PhpMAEException $e) {
            // Create plain-text error response
            $response = (new Response(500))
                ->withHeader('Content-Type', 'text/plain')
                ->write($e->getMessage());
        }

        $app->respond($response);
    }
}