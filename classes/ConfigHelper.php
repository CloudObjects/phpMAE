<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Pimple\Container;
use CloudObjects\SDK\NodeReader;

/**
 * The Config Helper helps phpMAE classes to load configuration
 * data from different CloudObjects objects.
 */
class ConfigHelper {

  private $app;
  private $validSources;
  private $reader;

  public function __construct(Container $app, $validSources) {
    $this->app = $app;
    $this->validSources = $validSources;
    $this->reader = new NodeReader([]);
  }

  /**
   * Get the value for a key, trying the sources in the order given by priorities.
   */
  public function get($key, $priorities, $default = null) {
    foreach ($priorities as $p) {
      if (in_array($p, $this->validSources)
            && $this->app[$p]) {
        $value = $this->reader->getFirstValueString($this->app[$p], $key);
        if (isset($value))
          return $value;
      }
    }

    return $default; // not found
  }

}
