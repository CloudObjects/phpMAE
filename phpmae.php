<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

require_once __DIR__."/vendor/autoload.php";
$app = new \Symfony\Component\Console\Application('phpMAE', '0.2.0');

$app->add(new ClassCreateCommand);
$app->add(new ClassDeployCommand);
$app->add(new ClassValidateCommand);
$app->add(new ClassTestEnvCommand);

$app->add(new DependenciesAddClassCommand);
$app->add(new DependenciesAddWebAPICommand);
$app->add(new DependenciesAddStaticTextCommand);
$app->add(new DependenciesAddAttachmentCommand);
$app->add(new DependenciesAddTemplateCommand);

$app->add(new TestEnvironmentStartCommand);
$app->run();
