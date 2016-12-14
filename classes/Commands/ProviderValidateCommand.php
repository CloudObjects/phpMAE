<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Cilex\Provider\Console\Command;
use CloudObjects\PhpMAE\ClassValidator;

class ProviderValidateCommand extends Command {

    protected function configure() {
      $this->setName('provider:validate')
        ->setDescription('Validates a source code file as service provider class.')
        ->addArgument('filename', InputArgument::REQUIRED, 'Name of PHP file');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
      $filename = $input->getArgument('filename');
      $validator = new ClassValidator();
      $validator->validateAsProvider(file_get_contents($filename));
      $output->writeln('Validated successfully.');
    }

}
