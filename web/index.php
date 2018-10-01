<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

date_default_timezone_set('UTC');

require __DIR__.'/../vendor/autoload.php';

$builder = new DI\ContainerBuilder();
$builder->addDefinitions(require __DIR__.'/../config.php');
$container = $builder->build();

$mode = $container->get('mode');
if ($mode == 'default') {
    // Default mode
    $container->get('CloudObjects\PhpMAE\Engine')
        ->run();
} elseif (substr($mode, 0, 7) == 'router:' || $mode == 'hybrid') {
    // Router or hybrid mode
    $container->get('CloudObjects\PhpMAE\Router')
        ->run();
} else {
    // Error
    die("Invalid mode!");
}