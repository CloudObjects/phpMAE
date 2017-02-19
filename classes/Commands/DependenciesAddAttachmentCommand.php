<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;

class DependenciesAddAttachmentCommand extends AbstractObjectCommand {

  protected function configure() {
    $this->setName('dependencies:add-attachment')
      ->setDescription('Adds an attachment to the specification of a class.')
      ->addArgument('coid-target', InputArgument::REQUIRED, 'The COID of the target class.')
      ->addArgument('filename', InputArgument::REQUIRED, 'The name of the file that should be attached.')
      ->addOption('upload', null, InputOption::VALUE_OPTIONAL, 'Calls "cloudobjects" to actually upload the file to CloudObjects.', true);
      // ->addOption('upload', null, InputOption::VALUE_OPTIONAL, 'Calls "cloudobjects" to create a configuration job for the updated configuration.', false);
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->parse($input->getArgument('coid-target'));
    $this->assertRDF();
    $this->assertPHPExists();

    $filename = $input->getArgument('filename');
    if (!file_exists($filename))
      throw new \Exception("File not found: ".$filename);

    $fileUri = "file:///".basename($filename);

    // Edit configuration
    $found = false;
    $object = $this->index[(string)$this->coid];
    if (isset($object['coid://phpmae.cloudobjects.io/usesAttachedFile'])) {
      foreach ($object['coid://phpmae.cloudobjects.io/usesAttachedFile'] as $o) {
        if ($o['type'] != 'uri') continue;
        if ($o['value'] == $fileUri) $found = true;
      }
      if (!$found)
        $object['coid://phpmae.cloudobjects.io/usesAttachedFile'][] = [ 'type' => 'uri', 'value' => $fileUri ];
    } else
      $object['coid://phpmae.cloudobjects.io/usesAttachedFile'] = [[ 'type' => 'uri', 'value' => $fileUri ]];

    // Persist configuration
    if (!$found) {
      $this->index[(string)$this->coid] = $object;    
      $this->updateRDFLocally($output);
    }

    if ($input->getOption('upload') !== null) {
        $output->writeln("Calling cloudobjects ...");
        passthru("cloudobjects attachment:put ".(string)$this->coid." ".$filename);
    }
  }

}
