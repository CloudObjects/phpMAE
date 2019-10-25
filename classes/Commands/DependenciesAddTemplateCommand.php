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

class DependenciesAddTemplateCommand extends AbstractAddDependenciesCommand {

    protected function configure() {
        $this->setName('dependencies:add-template')
            ->setDescription('Adds a Twig template dependency to the specification of a class.')
            ->addArgument('coid-target', InputArgument::REQUIRED, 'The COID of the target class.')
            ->addArgument('key', InputArgument::REQUIRED, 'The key for dependency injection.')
            ->addArgument('filename', InputArgument::REQUIRED, 'The filename for the Twig template.')
            ->addOption('upload', null, InputOption::VALUE_NONE, 'Calls "cloudobjects" to actually upload the template file to CloudObjects.');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->parse($input->getArgument('coid-target'));
        $this->dependencyPrecheck();

        // Check filename
        $filename = $input->getArgument('filename');
        if (!file_exists($filename))
            throw new \Exception("File not found: ".$filename);

        // Upload file if requested
        if ($input->getOption('upload')) {
            $output->writeln("Calling cloudobjects for file upload ...");
            passthru("cloudobjects attachment:put ".(string)$this->coid." ".$filename);
        }

        // Add dependency
        $this->addDependency(
            $input->getArgument('key'),
            'coid://phpmae.cloudobjects.io/TwigTemplateDependency',
            [
                'coid://phpmae.cloudobjects.io/usesAttachedTwigFile' => [
                    [ 'type' => 'uri', 'value' => "file:///".basename($filename) ]
                ]
            ],
            $input, $output
        );

        // Print documentation
        $key = $input->getArgument('key');
        $output->writeln("");
        $output->writeln("<info>Use your Twig template dependency:</info>");
        $output->writeln("");
        $output->writeln("1) Make sure you have access to the dependency injection container by adding the container to your class constructor.");
        $output->writeln("2) Request the template from the container using the key \"".$key."\".");
        $output->writeln("");
        $output->writeln("    private \$".$key."Template;");
        $output->writeln("");
        $output->writeln("    public function __construct(\Psr\Container\ContainerInterface \$container) {");
        $output->writeln("         \$this->".$key."Template = \$container->get('".$key."');");
        $output->writeln("    }");
        $output->writeln("");
        $output->writeln("3) Render your template by calling \$this->".$key."Template->render(\$context).");
        $output->writeln("");
    }

}
