<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Psr\Http\Message\RequestInterface, Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Http\Environment, Slim\Http\Uri, Slim\Http\Response;
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
                'wa' => 'coid://webapi.cloudobjects.io/'
            ]
        ]);

        $routes = $reader->getAllValuesNode($object, 'phpmae:hasRoute');
        
        foreach ($routes as $r) {
            if (!$reader->hasProperty($r, 'wa:hasVerb') || !$reader->hasProperty($r, 'wa:hasPath'))
                throw new PhpMAEException("Incomplete route configuration! Routes must have wa:hasVerb and wa:hasPath.");

            $engine = $this->engine;
            $app->map([ $reader->getFirstValueString($r, 'wa:hasVerb') ],
                $reader->getFirstValueString($r, 'wa:hasPath'),
                function(RequestInterface $request) use ($r, $reader, $engine) {
                    if ($reader->hasProperty($r, 'phpmae:runsClass')) {
                        $engine->loadRunClass($reader->getFirstValueIRI($r, 'phpmae:runsClass'));
                        return $engine->handle($request);
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
            $mode = $this->container->get('mode');
            $fallback = false;
    
            $app = new App;

            if ($mode == 'hybrid') {
                $mode = 'router:vhost';
                $fallback = true;
            }

            if (substr($mode, 0, 7) == 'router:') {
                try {
                    $coid = substr($mode, 7);
                    if ($coid == 'vhost') {
                        // Get router COID from namespace configuration
                        $env = new Environment($_SERVER);
                        $uri = Uri::createFromEnvironment($env);

                        $namespace = $this->objectRetriever->get('coid://'.$uri->getHost());
                        if ($router = $namespace->getProperty('coid://phpmae.cloudobjects.io/hasRouter'))
                            $coid = $router->getId();
                    }

                    $object = $this->objectRetriever->get($coid);
                    $this->configure($app, $object);
                } catch (\Exception $e) {
                    if ($fallback) {
                        $this->container->get(Engine::class)
                            ->run();
                        exit;
                    }
                }
            }

            $response = $app->run(true);
        } catch (PhpMAEException $e) {
            // Create plain-text error response
            $response = (new Response(500))
                ->withHeader('Content-Type', 'text/plain')
                ->write($e->getMessage());
        }

        $app->respond($response);
    }
}