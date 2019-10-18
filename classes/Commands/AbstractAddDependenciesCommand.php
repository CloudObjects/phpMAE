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
