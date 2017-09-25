<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

require_once __DIR__."/vendor/autoload.php";

$app = new \Cilex\Application('phpMAE', '0.1.0');
\CloudObjects\PhpMAE\TestEnvironmentManager::configure($app);

$app->command(new ControllerCreateCommand);
$app->command(new ControllerDeployCommand);
$app->command(new ControllerValidateCommand);
$app->command(new ControllerTestEnvCommand);
$app->command(new ControllerAddProviderCommand);

$app->command(new FunctionCreateCommand);
$app->command(new FunctionDeployCommand);
$app->command(new FunctionValidateCommand);
$app->command(new FunctionTestEnvCommand);

$app->command(new DependenciesAddWebAPICommand);
$app->command(new DependenciesAddAttachmentCommand);

$app->command(new ProviderCreateCommand);
$app->command(new ProviderDeployCommand);
$app->command(new ProviderValidateCommand);
$app->command(new ProviderTestEnvCommand);

$app->command(new TestEnvironmentStartCommand);
$app->run();
