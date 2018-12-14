<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;

class DependenciesAddTemplateCommand extends AbstractObjectCommand {

  protected function configure() {
    $this->setName('dependencies:add-template')
      ->setDescription('Adds a Twig template dependency to the specification of a class.')
      ->addArgument('coid-target', InputArgument::REQUIRED, 'The COID of the target class.')
      ->addArgument('key', InputArgument::REQUIRED, 'The key for dependency injection.')
      ->addArgument('filename', InputArgument::REQUIRED, 'The filename for the Twig template.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->parse($input->getArgument('coid-target'));
    $this->assertRDF();
    $this->assertPHPExists();

    // Check filename
    $filename = $input->getArgument('filename');
    if (!file_exists($filename))
      throw new \Exception("File not found: ".$filename);

    $fileUri = "file:///".basename($filename);

    // Create BNode with configuration
    $key = $input->getArgument('key');    
    $this->index['_:dep-'.$key] = [
      'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => [
        [ 'type' => 'uri', 'value' => 'coid://phpmae.cloudobjects.io/TwigTemplateDependency' ]
      ],
      'coid://phpmae.cloudobjects.io/hasKey' => [
        [ 'type' => 'literal', 'value' => $key ]
      ],
      'coid://phpmae.cloudobjects.io/usesAttachedTwigFile' => [
        [ 'type' => 'uri', 'value' => $fileUri ]
      ]
    ];
    
    // Edit configuration
    $object = $this->index[(string)$this->coid];
    if (isset($object['coid://phpmae.cloudobjects.io/hasDependency'])) {
      foreach ($object['coid://phpmae.cloudobjects.io/hasDependency'] as $o) {
        if ($o['type'] != 'bnode') continue;
        $bnode = $this->index[$o['value']];
        if (isset($bnode['coid://phpmae.cloudobjects.io/hasKey']) && $bnode['coid://phpmae.cloudobjects.io/hasKey'][0]['value'] == $key)
          throw new \Exception("A dependency with the key '".$key."' already exists.");
      }
      $object['coid://phpmae.cloudobjects.io/hasDependency'][] = [ 'type' => 'bnode', 'value' => '_:dep-'.$key ];
    } else
      $object['coid://phpmae.cloudobjects.io/hasDependency'] = [['type' => 'bnode', 'value' => '_:dep-'.$key ]];

    // Persist configuration
    $this->index[(string)$this->coid] = $object;    
    $this->updateRDFLocally($output);

  }

}
