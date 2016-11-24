<?php

use Silex\Application, Silex\ControllerProviderInterface;

class TestController1 implements ControllerProviderInterface {

  public function connect(Application $app) {
    $controllers = $app['controllers_factory'];

    $controllers->get('/', function() {
        return "Hello World!";
    });

    return $controllers;
  }
}