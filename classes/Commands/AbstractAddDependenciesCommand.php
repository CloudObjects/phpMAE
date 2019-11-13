<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AbstractAddDependenciesCommand extends AbstractObjectCommand {

    protected function configure() {
        $this->addOption('confjob', null, InputOption::VALUE_NONE, 'Calls "cloudobjects" to create a configuration job for the updated class.');
    }

    protected function dependencyPrecheck() {
        $this->assertRDF();
        $this->assertPHPExists();
    }

    protected function getObjectAndAssertType(string $coid, string $type) {
        // Retrieve configuration
        $config = shell_exec("cloudobjects get ".$coid);
        if (!isset($config))
            throw new \Exception("Could not retrieve <".$coid.">.");

        // Parse and validate configuration
        $parser = \ARC2::getRDFXMLParser();
        $parser->parse('', $config);
        $index = $parser->getSimpleIndex(false);
        if (!isset($index) || !isset($index[$coid])
                || !isset($index[$coid]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type']))
            throw new \Exception("<".$coid."> is not a valid CloudObjects object.");
      
        $hasType = false;
        foreach ($index[$coid]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $property => $values) {
            if ($values['value'] == $type) {
                $hasType = true;
                break;
            }
        }

        if (!$hasType)
            throw new \Exception("<".$coid."> must have the type <".$type.">.");
    }

    protected function addDependency(string $key, string $type, array $valuesToMerge,
            InputInterface $input, OutputInterface $output) {

        $this->index['_:dep-'.$key] = array_merge([
            'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' => [
                [ 'type' => 'uri', 'value' => $type ]
            ],
            'coid://phpmae.cloudobjects.io/hasKey' => [
                [ 'type' => 'literal', 'value' => $key ]
            ]            
        ], $valuesToMerge);
    
        // Edit configuration
        $object = $this->index[(string)$this->coid];
        if (isset($object['coid://phpmae.cloudobjects.io/hasDependency'])) {
            foreach ($object['coid://phpmae.cloudobjects.io/hasDependency'] as $o) {
                if ($o['type'] != 'bnode') continue;
                $bnode = $this->index[$o['value']];
                if (isset($bnode['coid://phpmae.cloudobjects.io/hasKey']) && $bnode['coid://phpmae.cloudobjects.io/hasKey'][0]['value'] == $key)
                    throw new \Exception("A dependency with the key '".$key."' already exists.");

                foreach ($valuesToMerge as $k => $values)
                    if (isset($bnode[$k]))
                        foreach ($bnode[$k] as $a)
                            foreach ($valuesToMerge[$k] as $b)
                                if ($a['type'] == $b['type'] && $a['value'] == $b['value'])
                                    throw new \Exception("This dependency was already added.");
            }
            $object['coid://phpmae.cloudobjects.io/hasDependency'][] = [ 'type' => 'bnode', 'value' => '_:dep-'.$key ];
        } else
            $object['coid://phpmae.cloudobjects.io/hasDependency'] = [['type' => 'bnode', 'value' => '_:dep-'.$key ]];

        // Persist configuration
        $this->index[(string)$this->coid] = $object;    
        $this->updateRDFLocally($output);

        if ($input->getOption('confjob'))
            $this->createConfigurationJob($output);   
    }

}
