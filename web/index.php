<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

date_default_timezone_set('UTC');

require __DIR__.'/../vendor/autoload.php';

$app = new Slim\App();

$builder = new DI\ContainerBuilder();
$builder->addDefinitions(require __DIR__.'/../config.php');
$app = new Slim\App($builder->build());
$engine = $app->getContainer()->get('CloudObjects\PhpMAE\Engine');

$engine->run();