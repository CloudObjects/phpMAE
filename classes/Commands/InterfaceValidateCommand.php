<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\PhpMAE\ClassValidator;

class InterfaceValidateCommand extends AbstractObjectCommand {

  protected function configure() {
    $this->setName('interface:validate')
      ->setDescription('Validates an interface for the phpMAE.')
      ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.')
      ->addOption('watch', null, InputOption::VALUE_OPTIONAL, 'Keep watching for changes of the file and revalidate automatically.', null);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->parse($input->getArgument('coid'));
    $this->assertRDF();
    if (!in_array('coid://phpmae.dev/Interface', $this->rdfTypes))
      throw new \Exception("Object does not have the valid interface type.");
    $this->assertPHPExists();

    // Running validator
    $validator = new ClassValidator;
    try {
        $validator->validateInterface(file_get_contents($this->phpFileName), $this->coid);
        $output->writeln("Validated successfully.");
    } catch (\Exception $e) {
        $output->writeln('<error>'.get_class($e).'</error> '.$e->getMessage());
    }

    if ($input->getOption('watch') !== null) {
        $cmd = $this;
        $this->watchPHPFile($output, function() use ($validator, $cmd, $output) {
            try {
              $validator->validateInterface(file_get_contents($cmd->phpFileName), $this->coid);
              $output->writeln("Validated successfully.");
            } catch (\Exception $e) {
              $output->writeln('<error>'.get_class($e).'</error> '.$e->getMessage());
            }
        });
    }
  }

}
