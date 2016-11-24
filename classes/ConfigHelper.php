<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Silex\Application;

/**
 * The Config Helper helps phpMAE classes to load configuration
 * data from different CloudObjects objects.
 */
class ConfigHelper {

  private $app;
  private $validSources;

  public function __construct(Application $app, $validSources) {
    $this->app = $app;
    $this->validSources = $validSources;
  }

  /**
   * Get the value for a key, trying the sources in the order given by priorities.
   */
  public function get($key, $priorities, $default = null) {
    foreach ($priorities as $p) {
      if (in_array($p, $this->validSources)
          && isset($this->app[$p])
          && $this->app[$p]->getProperty($key))
        return $this->app[$p]->getProperty($key)->getValue();
    }

    return $default; // not found
  }

}
