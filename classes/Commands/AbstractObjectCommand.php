<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Cilex\Provider\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;

abstract class AbstractObjectCommand extends Command {

  protected $coid;
  protected $fullName;
  protected $rdfTypes;
  protected $index;

  protected function parse($coid) {
    $this->coid = COIDParser::fromString($coid);

    if (COIDParser::getType($this->coid)==COIDParser::COID_INVALID)
      throw new \Exception("Invalid COID: ".$coid);

    $name = COIDParser::getName($this->coid);
    $version = COIDParser::getVersion($this->coid);
    $this->fullName = isset($version) ? $name.".".$version : $name;
    $this->phpFileName = $this->fullName.'.php';
  }

  protected function assertRDF() {
    if (!file_exists($this->fullName.'.xml'))
      throw new \Exception("File not found: ".$this->fullName.".xml");

    $parser = \ARC2::getRDFXMLParser();
  	$parser->parse('', file_get_contents($this->fullName.'.xml'));
  	$index = $parser->getSimpleIndex(false);
    $id = (string)$this->coid;
    if (!isset($index) || !isset($index[$id])
        || !isset($index[$id]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type']))
      throw new \Exception($this->fullName.".xml does not contain a valid RDF description of the object.");

    $this->rdfTypes = [];
    foreach ($index[$id]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $o)
      if ($o['type'] == 'uri') $this->rdfTypes[] = $o['value'];
    $this->index = $index;
  }

  protected function updateRDFLocally(OutputInterface $output) {
    file_put_contents($this->fullName.".xml",
      $this->getSerializer()->getSerializedIndex($this->index));
    $output->writeln("Updated ".$this->fullName.".xml.");
  }

  protected function createConfigurationJob(OutputInterface $output) {
    $output->writeln("Calling cloudobjects configuration-job:create ...");
    passthru("cloudobjects configuration-job:create ".$this->fullName.".xml");
  }

  protected function assertPHPExists() {
    if (!file_exists($this->phpFileName))
      throw new \Exception("File not found: ".$this->phpFileName);
  }

  protected function getSerializer() {
    return \ARC2::getRDFXMLSerializer(array(
      'serializer_type_nodes' => true,
      'ns' => array(
        'co' => 'coid://cloudobjects.io/',
        'phpmae' => 'coid://phpmae.cloudobjects.io/'
      )
    ));
  }

  protected function watchPHPFile(OutputInterface $output, callable $callable) {
    $fileTime = filemtime($this->phpFileName);
    $output->writeln('Watching for changes ...');
    while (true) {
      clearstatcache();
      if (filemtime($this->phpFileName)!=$fileTime) {
        // File has changed ...
        sleep(1);
        $fileTime = filemtime($this->phpFileName);
        $callable();    
        $output->writeln('Watching for changes ...');
      }
      sleep(0.5);
    }
  }

}
