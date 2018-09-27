<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

require_once __DIR__."/vendor/autoload.php";

$app = new \Cilex\Application('phpMAE', '0.2.0');
\CloudObjects\PhpMAE\TestEnvironmentManager::configure($app);

$app->command(new ClassCreateCommand);
$app->command(new ClassDeployCommand);
$app->command(new ClassValidateCommand);
//$app->command(new ClassTestEnvCommand);
// $app->command(new ClassAddProviderCommand);

$app->command(new DependenciesAddWebAPICommand);
$app->command(new DependenciesAddAttachmentCommand);

$app->command(new TestEnvironmentStartCommand);
$app->run();
