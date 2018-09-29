<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;

class DependenciesAddClassCommand extends AbstractObjectCommand {

  protected function configure() {
    $this->setName('dependencies:add-class')
      ->setDescription('Adds a class dependency to the specification of a class.')
      ->addArgument('coid-target', InputArgument::REQUIRED, 'The COID of the target class.')
      ->addArgument('key', InputArgument::REQUIRED, 'The key for dependency injection.')
      ->addArgument('coid-class', InputArgument::REQUIRED, 'The COID of the dependency class.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->parse($input->getArgument('coid-target'));
    $this->assertRDF();
    $this->assertPHPExists();

    // Check Class
    $coidClass = COIDParser::fromString($input->getArgument('coid-class'));
    if (COIDParser::getType($coidClass)!=COIDParser::COID_VERSIONED
        && COIDParser::getType($coidClass)!=COIDParser::COID_UNVERSIONED)
      throw new \Exception("Invalid COID: ".$coid);

    // Create BNode with configuration
    $key = $input->getArgument('key');    
    $this->index['_:dep-'.$key] = [
      'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => [
        [ 'type' => 'uri', 'value' => 'coid://phpmae.cloudobjects.io/ClassDependency' ]
      ],
      'coid://phpmae.cloudobjects.io/hasKey' => [
        [ 'type' => 'literal', 'value' => $key ]
      ],
      'coid://phpmae.cloudobjects.io/hasClass' => [
        [ 'type' => 'uri', 'value' => (string)$coidClass ]
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
