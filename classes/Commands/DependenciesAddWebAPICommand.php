<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;

class DependenciesAddWebAPICommand extends AbstractAddDependenciesCommand {

    protected function configure() {
        $this->setName('dependencies:add-webapi')
            ->setDescription('Adds a dependency to the specification of a class.')
            ->addArgument('coid-target', InputArgument::REQUIRED, 'The COID of the target class.')
            ->addArgument('key', InputArgument::REQUIRED, 'The key for dependency injection.')
            ->addArgument('coid-webapi', InputArgument::REQUIRED, 'The COID of the Web API.');
        
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->parse($input->getArgument('coid-target'));
        $this->dependencyPrecheck();

        // Check Web API
        $coidWebAPI = COIDParser::fromString($input->getArgument('coid-webapi'));
        if (COIDParser::getType($coidWebAPI) != COIDParser::COID_VERSIONED
                && COIDParser::getType($coidWebAPI) != COIDParser::COID_UNVERSIONED)
            throw new \Exception("Invalid COID: ".$coid);

        // Add dependency
        $this->addDependency(
            $input->getArgument('key'),
            'coid://phpmae.cloudobjects.io/WebAPIDependency',
            [
                'coid://phpmae.cloudobjects.io/hasAPI' => [
                    [ 'type' => 'uri', 'value' => (string)$coidWebAPI ]
                ]
            ],
            $input, $output
        ); 
    }

}
