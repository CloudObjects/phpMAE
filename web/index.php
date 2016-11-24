<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

date_default_timezone_set('UTC');

require "../vendor/autoload.php";

ini_set("display_errors", 1);
error_reporting(E_ALL & ~E_NOTICE);

$app = new Silex\Application();
$app['debug'] = true;
CloudObjects\PhpMAE\Runner::configure($app,
	Symfony\Component\HttpFoundation\Request::createFromGlobals(),
	require "../config.php");
$app->run();
