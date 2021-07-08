<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use InvalidArgumentException;
use Psr\Http\Message\RequestInterface, Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\App;
use Tuupola\Middleware\CorsMiddleware;;
use Slim\Http\Environment, Slim\Http\Uri, Slim\Http\Response, Slim\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\ServerRequest;
use ML\IRI\IRI;
use ML\JsonLD\Node, ML\JsonLD\JsonLD;
use CloudObjects\Utilities\RDF\Arc2JsonLdConverter;
use CloudObjects\SDK\NodeReader, CloudObjects\SDK\ObjectRetriever;
use CloudObjects\SDK\JSON\SchemaValidator;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;
use CloudObjects\PhpMAE\ObjectRetrieverPool;

class Router {

    const STATIC_CACHE_TTL = 60;

    private $engine;
    private $objectRetriever;
    private $container;

    public function __construct(Engine $engine, ObjectRetriever $objectRetriever,
            ContainerInterface $container) {
        
        $this->engine = $engine;
        $this->objectRetriever = $objectRetriever;
        $this->container = $container;
    }

    private function configure(App $app, Node $object) {
        $reader = new NodeReader([
            'prefixes' => [
                'phpmae' => 'coid://phpmae.dev/',
                'wa' => 'coid://webapis.co-n.net/',
                'agws' => 'coid://aauid.net/',
            ]
        ]);

        if ($reader->hasProperty($object, 'phpmae:enableCORSWithOrigin')) {
            // Add CORS support to router
            $origins = $reader->getAllValuesString($object, 'phpmae:enableCORSWithOrigin');
            $app->add(new CorsMiddleware([
                'origin' => $origins,
                'headers.allow' => [ 'Content-Type' ],
                'credentials' => true,
                'cache' => 600
            ]));
        }

        $routes = array_merge(
            $reader->getAllValuesNode($object, 'phpmae:hasRoute'),
            $reader->getAllValuesNode($object, 'agws:hasMethod')
        );

        foreach ($routes as $r) {
            if (!$reader->hasProperty($r, 'wa:hasVerb') || !$reader->hasProperty($r, 'wa:hasPath'))
                throw new PhpMAEException("Incomplete route configuration! Routes must have wa:hasVerb and wa:hasPath.");

            $engine = $this->engine;
            $retriever = $this->getObjectRetriever(new IRI($object->getId()));
            $app->map([ $reader->getFirstValueString($r, 'wa:hasVerb') ],
                $reader->getFirstValueString($r, 'wa:hasPath'),
                function(RequestInterface $request, ResponseInterface $response, $args) use ($r, $reader, $engine, $retriever, $object) {
                    if ($reader->hasProperty($r, 'phpmae:runsClass')) {
                        try {
                            $params = [];
                            if ($reader->hasProperty($r, 'wa:hasJSONBodyWithSchema')) {
                                // Validate request against schema
                                $schemaValidator = new SchemaValidator($retriever);
                                $schemaValidator->validateAgainstCOID($request->getParsedBody(),
                                    $reader->getFirstValueIRI($r, 'wa:hasJSONBodyWithSchema'));
                                // Then, map to single argument
                                $params = [ $request->getParsedBody() ];
                            } elseif (is_array($args) && count($args) > 0)  {
                                // Use path arguments as params
                                $params = $args;
                            } elseif (is_array($request->getParsedBody())) {
                                // Use body as params
                                $params = $request->getParsedBody();
                            } elseif (is_array($request->getQueryParams())) {
                                // Use query as params
                                $params = $request->getQueryParams();
                            }
                        } catch (InvalidArgumentException $e) {
                            return (new Response(400))->withJson([
                                'error' => 'InputValidationFailed',
                                'message' => $e->getMessage()
                            ]);
                        }

                        // Add static parameters from Router
                        foreach ($reader->getAllValuesNode($r, 'phpmae:includeParameterWithDefault') as $p) {
                            if (!$reader->hasProperty($p, 'wa:hasKey') || !$reader->hasProperty($p, 'wa:hasDefaultValue')) {
                                // Ignore incomplete parameter
                                continue;
                            }

                            if ($reader->hasType($p, 'wa:HeaderParameter')) {
                                // Add custom header
                                $request = $request->withHeader(
                                    'HTTP_'.strtoupper($reader->getFirstValueString($p, 'wa:hasKey')),
                                    $reader->getFirstValueString($p, 'wa:hasDefaultValue')
                                );
                            }
                        }

                        // Route is mapped to a class
                        $engine->loadRunClass($reader->getFirstValueIRI($r, 'phpmae:runsClass'), $request);

                        if ($reader->hasProperty($r, 'phpmae:runsMethod')) {
                            // Calls to specific methods must be rewritten
                            // Path, request and query parameters are merged and used as method input
                            $rpcBody = json_encode([
                                'jsonrpc' => '2.0',
                                'method' => $reader->getFirstValueString($r, 'phpmae:runsMethod'),
                                'params' => $params,
                                'id' => 'R'
                            ]);
                            $innerRequest = new ServerRequest('POST', $request->getUri(),
                                $request->getHeaders(), $rpcBody);

                            $innerResponse = $engine->handle($innerRequest);
                            if ($innerResponse->hasHeader('C-PhpMae-Passthru'))
                                return $innerResponse->withoutHeader('C-PhpMae-Passthru');

                            $rpcResponse = json_decode($innerResponse->getBody(), true);

                            if (isset($rpcResponse['result'])) {
                                return $engine->generateResponse($rpcResponse['result']);
                            } else {
                                return (new Response(500))->withJson($rpcResponse);
                            }
                        } else {
                            // Generic class execution (invokable)
                            return $engine->handle($request, $params);
                        }

                    } elseif ($reader->hasProperty($r, 'phpmae:redirectsToURL')) {
                        // Route to redirect to an external URL
                        return (new Response)->withRedirect($reader->getFirstValueString($r, 'phpmae:redirectsToURL'));

                    } elseif ($reader->hasProperty($r, 'phpmae:proxiesRequestsToBaseURL')) {
                        // Route to proxy requests to an external URL
                        $client = new Client([
                            'base_uri' => $reader->getFirstValueString($r, 'phpmae:proxiesRequestsToBaseURL')
                        ]);
                        $uri = $request->getUri();
                        try {
                            return $client->request($request->getMethod(),
                                $uri->getPath() . ($uri->getQuery() != '' ? '?'.$uri->getQuery() : ''), [
                                    'headers' => $request->getHeaders(),
                                    'body' => $request->getBody()
                                ]);
                        } catch (BadResponseException $e) {
                            return $e->getResponse();
                        }

                    } elseif ($reader->hasProperty($r, 'phpmae:servesStaticFileAttachment')) {
                        // Rule to serve an attached file
                        $etag = '"'.md5($reader->getFirstValueString($object, 'co:isAtRevision')
                            .'+'.$reader->getFirstValueString($r, 'phpmae:servesStaticFileAttachment')).'"';

                        if ($request->hasHeader('If-None-Match') && strpos($request->getHeaderLine('If-None-Match'), $etag) > -1) {
		    	            // Can use cached version
                            return (new Response(304))->withHeader('ETag', $etag)
                                ->withHeader('Cache-Control', 'max-age='.self::STATIC_CACHE_TTL.', public');
                        }

                        $attachmentContent = $retriever->getAttachment(new IRI($object->getId()),
                            $reader->getFirstValueString($r, 'phpmae:servesStaticFileAttachment'));

                        if (!isset($attachmentContent))
                            return (new Response(501))->write("Requested static file attachment cannot be found.");

                        return $engine->generateResponse($attachmentContent)
                            ->withHeader('ETag', $etag)
                            ->withHeader('Cache-Control', 'max-age='.self::STATIC_CACHE_TTL.', public');
                    } else {
                        // Route has no implementation
                        return (new Response(501))->write("Route implementation not available or no access granted.");
                    }
                });
        }
    }

    private function getObjectRetriever(IRI $coid) {
        return $this->container->get(ObjectRetrieverPool::class)
            ->getObjectRetriever($coid->getHost());
    }

    private function getRouter(IRI $coid) {
        return $this->getObjectRetriever($coid)
            ->get($coid);
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
                            if (isset($namespace) && $routerCoid = $namespace->getProperty('coid://phpmae.dev/hasRouter'))
                                $router = $this->getRouter(new IRI($routerCoid->getId()));
                        }
                        break;
                    case 'router:header':
                        if ($router == null) {
                            $request = Request::createFromEnvironment($env);
                            if ($request->hasHeader('C-PhpMae-Router-COID'))
                                $router = $this->getRouter(new IRI($request->getHeaderLine('C-PhpMae-Router-COID')));
                        }
                        break;
                    case 'default':
                        $fallback = true;
                        break;
                    default:
                        if (substr($m, 0, 14) == 'router:coid://')
                            $router = $this->getRouter(new IRI(substr($m, 7)));
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