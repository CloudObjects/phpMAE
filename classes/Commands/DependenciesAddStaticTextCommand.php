<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;

class DependenciesAddStaticTextCommand extends AbstractAddDependenciesCommand {

  	protected function configure() {
    	$this->setName('dependencies:add-text')
      		->setDescription('Adds a static text dependency to the specification of a class.')
      		->addArgument('coid-target', InputArgument::REQUIRED, 'The COID of the target class.')
      		->addArgument('key', InputArgument::REQUIRED, 'The key for dependency injection.')
      		->addArgument('value', InputArgument::REQUIRED, 'The text value.');
		  
		parent::configure();
  	}

  	protected function execute(InputInterface $input, OutputInterface $output) {
    	$this->parse($input->getArgument('coid-target'));
    	$this->assertRDF();
    	$this->dependencyPrecheck();

    	// Add dependency
        $this->addDependency(
            $input->getArgument('key'),
            'coid://phpmae.cloudobjects.io/StaticTextDependency',
            [
                'coid://phpmae.cloudobjects.io/hasValue' => [
                    [ 'type' => 'literal', 'value' => $input->getArgument('value') ]
                ]
            ],
            $input, $output
		);

		// Print documentation
        $key = $input->getArgument('key');
        $output->writeln("");
        $output->writeln("<info>Use your static text dependency:</info>");
        $output->writeln("");
        $output->writeln("1) Make sure you have access to the dependency injection container by adding the container to your class constructor.");
        $output->writeln("2) Request the value string from the container using the key \"".$key."\".");
        $output->writeln("");
        $output->writeln("    private \$".$key.";");
        $output->writeln("");
        $output->writeln("    public function __construct(\Psr\Container\ContainerInterface \$container) {");
        $output->writeln("         \$this->".$key." = \$container->get('".$key."');");
        $output->writeln("    }");
        $output->writeln("");
        $output->writeln("3) Use \$this->".$key." wherever required.");
        $output->writeln("");    
  	}

}
