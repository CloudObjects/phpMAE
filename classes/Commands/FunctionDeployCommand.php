<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\PhpMAE\ClassValidator;

class FunctionDeployCommand extends AbstractObjectCommand {

  protected function configure() {
    $this->setName('function:deploy')
      ->setDescription('Validates a function class for the phpMAE and uploads it into CloudObjects. Updates the configuration if necessary.')
      ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->parse($input->getArgument('coid'));
    $this->assertRDF();
    if (!in_array('coid://phpmae.cloudobjects.io/FunctionClass', $this->rdfTypes))
      throw new \Exception("Object is not a function.");
    $this->assertPHPExists();

    // Running validator
    $validator = new ClassValidator();
    $validator->validateAsFunction(file_get_contents($this->fullName.'.php'));
    $output->writeln("Validated successfully, calling cloudobjects ...");

    passthru("cloudobjects attachment:put ".(string)$this->coid." ".$this->fullName.".php");

    // Updates configuration if necessary
    $object = $this->index[(string)$this->coid];
    if (!isset($object['coid://phpmae.cloudobjects.io/hasSourceFile'])) {
      $object['coid://phpmae.cloudobjects.io/hasSourceFile'] = [[
        'value' => 'file:///'.$this->fullName.'.php',
        'type' => 'uri'
      ]];
      $this->index[(string)$this->coid] = $object;
      $this->updateRDFLocally($output);
      $this->createConfigurationJob($output);
    }
  }

}
