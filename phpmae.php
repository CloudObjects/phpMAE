<?php

require_once __DIR__."/vendor/autoload.php";

$app = new Cilex\Application('phpMAE', '0.1.0');
CloudObjects\PhpMAE\TestEnvironmentManager::configure($app);
$app->command(new CloudObjects\PhpMAE\Commands\ControllerCreateCommand());
$app->command(new CloudObjects\PhpMAE\Commands\ControllerDeployCommand());
$app->command(new CloudObjects\PhpMAE\Commands\ControllerValidateCommand());
$app->command(new CloudObjects\PhpMAE\Commands\ControllerAddProviderCommand());
$app->command(new CloudObjects\PhpMAE\Commands\ProviderValidateCommand());
$app->command(new CloudObjects\PhpMAE\Commands\TestEnvironmentStartCommand());
$app->command(new CloudObjects\PhpMAE\Commands\TestEnvironmentUploadCommand());
$app->run();
