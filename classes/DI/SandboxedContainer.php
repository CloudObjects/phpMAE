<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\DI;

use Psr\Container\ContainerInterface;

/**
 * The SandboxedContainer is a proxy class for a PSR-11 container that
 * ensures that only interface methods are available.
 */
class SandboxedContainer implements ContainerInterface {

    private $container;
    
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    public function has($id) {
        return $this->container->has($id);
    }

    public function get($id) {
        return $this->container->get($id);
    }

}
