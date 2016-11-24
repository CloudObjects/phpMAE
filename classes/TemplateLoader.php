<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use CloudObjects\SDK\ObjectRetriever;
use ML\IRI\IRI;

class TemplateLoader implements \Twig_LoaderInterface {

  private $repository;
  private $object;

  public function __construct(ObjectRetriever $repository, IRI $object) {
    $this->repository = $repository;
    $this->object = $object;
  }

  public function	getSource($name) {
    return $this->repository->getAttachment($this->object, $name);
  }

  public function getCacheKey($name) {
    return (string)$this->object.'#'.$name;
  }

  public function isFresh($name, $time) {
    return true; // TODO: implement something here?!
  }

}
