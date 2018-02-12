<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Cilex\Provider\Console\Command;
use Defuse\Crypto\Key;

class HelpersGenerateKeyCommand extends Command {

    protected function configure() {
      $this->setName('helpers:generate-key')
        ->setDescription('Generates a random key that can be used with the defuse/php-encryption library.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $key = Key::createNewRandomKey();
        $output->writeln($key->saveToAsciiSafeString());      
    }

}
