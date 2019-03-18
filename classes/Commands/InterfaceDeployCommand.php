<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;
use CloudObjects\PhpMAE\ClassValidator;

class InterfaceDeployCommand extends AbstractObjectCommand {

  protected function configure() {
    $this->setName('interface:deploy')
      ->setDescription('Validates an interface for the phpMAE and uploads it into CloudObjects. Updates the configuration if necessary.')
      ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->parse($input->getArgument('coid'));
    $this->assertRDF();
    if (!in_array('coid://phpmae.cloudobjects.io/Interface', $this->rdfTypes))
      throw new \Exception("Object does not have athevalid interface type.");
    $this->assertPHPExists();

    // Running validator
    $validator = new ClassValidator();
    $validator->validateInterface(file_get_contents($this->fullName.'.php'), $this->coid);
    $output->writeln("Validated successfully, calling cloudobjects ...");

    passthru("cloudobjects attachment:put ".(string)$this->coid." ".$this->fullName.".php");

    // Updates configuration if necessary
    if ($this->ensureFilenameInConfig($output, true)) {
      $this->createConfigurationJob($output);
    }

    // Print URL so developer can easily access it
    $output->writeln("");
    $visibility = isset($object['coid://cloudobjects.io/isVisibleTo'])
      ? $object['coid://cloudobjects.io/isVisibleTo'][0]['value']
      : 'coid://cloudobjects.io/Vendor';
    $path = $this->coid->getHost().$this->coid->getPath();
  }

}
