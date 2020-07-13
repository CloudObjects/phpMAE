<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;

class DependenciesAddClassCommand extends AbstractAddDependenciesCommand {

    protected function configure() {
        $this->setName('dependencies:add-class')
            ->setDescription('Adds a class dependency to the specification of a class.')
            ->addArgument('coid-target', InputArgument::REQUIRED, 'The COID of the target class.')
            ->addOption('key', null, InputArgument::OPTIONAL, 'The key for dependency injection. Randomly generated if not provided.', null)
            ->addArgument('coid-class', InputArgument::REQUIRED, 'The COID of the dependency class.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->requireCloudObjectsCLI();

        $this->parse($input->getArgument('coid-target'));
        $this->dependencyPrecheck();

        // Check Class
        $coidClass = COIDParser::fromString($input->getArgument('coid-class'));
        if (COIDParser::getType($coidClass) != COIDParser::COID_VERSIONED
                && COIDParser::getType($coidClass) != COIDParser::COID_UNVERSIONED)
            throw new \Exception("Invalid COID: ".$coid);

        // Checking type
        $output->writeln("Fetching configuration for ".(string)$coidClass." ...");
        $this->getObjectAndAssertType((string)$coidClass, 'coid://phpmae.cloudobjects.io/Class');

        // Add dependency
        $this->addDependency(
            $input->getOption('key') ? $input->getOption('key') : uniqid('class-'),
            'coid://phpmae.cloudobjects.io/ClassDependency',
            [
                'coid://phpmae.cloudobjects.io/hasClass' => [
                    [ 'type' => 'uri', 'value' => (string)$coidClass ]
                ]
            ],
            $input, $output
        );

        // Print documentation
        $className = COIDParser::getName($coidClass);
        $key = $input->getOption('key') ? $input->getOption('key')
            : strtolower($className[0]).substr($className, 1);
        $output->writeln("");
        $output->writeln("<info>Use your class dependency:</info>");
        $output->writeln("");
        $output->writeln("1) Add the class ".$className." to your constructor parameters.");
        $output->writeln("2) Assign the class to \$this->".$key.".");
        $output->writeln("");
        $output->writeln("    private \$".$key.";");
        $output->writeln("");
        $output->writeln("    public function __construct(".$className." \$".$key.") {");
        $output->writeln("        \$this->".$key." = \$".$key.";");
        $output->writeln("    }");
        $output->writeln("");
        $output->writeln("3) Use \$this->".$key." wherever required.");
        $output->writeln("");
        $output->writeln("You can find the documentation for the available class methods on this page:");
        $output->writeln("➡️  https://cloudobjects.io/".$coidClass->getHost().$coidClass->getPath());
        $output->writeln("");
    }

}
