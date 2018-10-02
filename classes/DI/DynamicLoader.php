<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\DI;

use CloudObjects\SDK\ObjectRetriever;
use CloudObjects\PhpMAE\Engine, CloudObjects\PhpMAE\ClassRepository;

/**
 * The DynamicLoader allows accessing other classes that are not
 * statically defined as dependencies.
 */
class DynamicLoader {

    private $retriever;
    private $repository;

    public function __construct(ObjectRetriever $retriever, ClassRepository $repository) {
        $this->retriever = $retriever;
        $this->repository = $repository;
    }

    public function get($coid) {
        $object = $this->retriever->get($coid);
        return isset($object)
            ? $this->repository->createInstance($object)->get(Engine::SKEY)
            : null;
    }

}
