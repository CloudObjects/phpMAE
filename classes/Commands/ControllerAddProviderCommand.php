<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;

class ControllerAddProviderCommand extends AbstractObjectCommand {

  protected function configure() {
    $this->setName('controller:add-provider')
      ->setDescription('Adds a provider class to the specification for the controller class.')
      ->addArgument('coid-controller', InputArgument::REQUIRED, 'The COID of the controller.')
      ->addArgument('coid-provider', InputArgument::REQUIRED, 'The COID of the provider.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->parse($input->getArgument('coid-controller'));
    $this->assertRDF();
    if (!in_array('coid://phpmae.cloudobjects.io/ControllerClass', $this->rdfTypes))
      throw new \Exception("Object is not a controller.");
    $this->assertPHPExists();

    // Check provider
    $coidProvider = COIDParser::fromString($input->getArgument('coid-provider'));
    if (COIDParser::getType($coidProvider)==COIDParser::COID_INVALID)
      throw new \Exception("Invalid COID: ".$coid);

    // Edit RDF
    $object = $this->index[(string)$this->coid];
    if (isset($object['coid://phpmae.cloudobjects.io/usesProvider'])) {
      foreach ($object['coid://phpmae.cloudobjects.io/usesProvider'] as $o)
        if ($o['type'] == 'uri' && $o['value'] == (string)$coidProvider) {
          $output->writeln("This provider has already been added.");
          return;
        }
      $object['coid://phpmae.cloudobjects.io/usesProvider'][] = array(
        'type' => 'uri',
        'value' => (string)$coidProvider
      );
    } else {
      $object['coid://phpmae.cloudobjects.io/usesProvider'] = array(array(
        'type' => 'uri',
        'value' => (string)$coidProvider
      ));
    }
    $this->index[(string)$this->coid] = $object;
    file_put_contents($this->fullName.".xml",
      $this->getSerializer()->getSerializedIndex($this->index));
    $output->writeln("Updated ".$this->fullName.".xml.");
  }

}
