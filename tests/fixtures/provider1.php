<?php

use Silex\Application, Silex\ServiceProviderInterface;

class TestProvider1 implements ServiceProviderInterface {

  public function register(Application $app) {
    // implement this
  }

  public function boot(Application $app) {
    // implement this
  }
}