<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use ML\IRI\IRI;
use CloudObjects\SDK\COIDParser;
use CloudObjects\PhpMAE\CredentialManager;

abstract class AbstractObjectCommand extends Command {

  protected $coid;
  protected $fullName;
  protected $phpFileName;
  protected $xmlFileName;
  protected $rdfTypes;
  protected $index;

  protected function parse($coid) {
    if (!CredentialManager::isConfigured())
        throw new \Exception("The 'cloudobjects' CLI tool must be installed and authorized.");

    $this->coid = COIDParser::fromString($coid);

    if (COIDParser::getType($this->coid)!=COIDParser::COID_VERSIONED
        && COIDParser::getType($this->coid)!=COIDParser::COID_UNVERSIONED)
      throw new \Exception("Invalid COID: ".(string)$this->coid);

    $name = COIDParser::getName($this->coid);
    $version = COIDParser::getVersion($this->coid);
    $this->fullName = isset($version) ? $name.".".$version : $name;
    $this->phpFileName = $this->fullName.'.php';
    $this->xmlFileName = $this->fullName.'.xml';
  }

  protected function assertRDF() {
    if (!file_exists($this->xmlFileName))
      throw new \Exception("File not found: ".$this->xmlFileName);

    $parser = \ARC2::getRDFXMLParser();
  	$parser->parse('', file_get_contents($this->xmlFileName));
  	$index = $parser->getSimpleIndex(false);
    $id = (string)$this->coid;
    if (!isset($index) || !isset($index[$id])
        || !isset($index[$id]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type']))
      throw new \Exception($this->xmlFileName." does not contain a valid RDF description of the object.");

    $this->rdfTypes = [];
    foreach ($index[$id]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $o)
      if ($o['type'] == 'uri') $this->rdfTypes[] = $o['value'];
    $this->index = $index;
  }

  protected function updateRDFLocally(OutputInterface $output) {
    file_put_contents($this->xmlFileName,
      $this->getSerializer()->getSerializedIndex($this->index));
    $output->writeln("Updated ".$this->xmlFileName);
  }

  protected function createConfigurationJob(OutputInterface $output) {
    $output->writeln("Calling cloudobjects configuration-job:create ...");
    passthru("cloudobjects configuration-job:create ".$this->xmlFileName);
  }

  protected function ensureFilenameInConfig(OutputInterface $output, $interface = false) {
    $property = ($interface ? 'coid://phpmae.cloudobjects.io/hasDefinitionFile'
      : 'coid://phpmae.cloudobjects.io/hasSourceFile');
    $object = $this->index[(string)$this->coid];
    if (!isset($object[$property])) {
      $object[$property] = [[
        'value' => 'file:///'.$this->fullName.'.php',
        'type' => 'uri'
      ]];
      $this->index[(string)$this->coid] = $object;
      $this->updateRDFLocally($output);
      return true;
    } else
      return false;
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
    set_time_limit(0);
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

  protected function getAdditionalTypes() {
		$coids = [];
		foreach ($this->rdfTypes as $t) {
      if (in_array($t, [ 'coid://phpmae.cloudobjects.io/Class', 'coid://phpmae.cloudobjects.io/HTTPInvokableClass',
          'coid://phpmae.cloudobjects.io/Interface' ])) continue;
			$coids[] = new IRI($t);
		}

		return $coids;
	}
}
