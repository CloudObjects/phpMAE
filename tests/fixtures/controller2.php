<?php

use Silex\Application, Silex\Api\ControllerProviderInterface;

class TestController2 implements ControllerProviderInterface {

  public function connect(Application $app) {
    $controllers = $app['controllers_factory'];

    $controllers->get('/', function(ClassValidator $x) {
        return "Hello World!";
    });

    return $controllers;
  }
}