<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Cilex\Command\Command;
use CloudObjects\SDK\COIDParser;

class ControllerCreateCommand extends Command {

    protected function configure() {
      $this->setName('controller:create')
        ->setDescription('Create a new controller class for the phpMAE.')
        ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.')
        ->addOption('force', 'f', InputOption::VALUE_OPTIONAL, 'Forces new object creation and replaces existing files.', false)
        ->addOption('confjob', null, InputOption::VALUE_OPTIONAL, 'Calls "cloudobjects" to create a configuration job for the new controller.', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
      $coid = COIDParser::fromString($input->getArgument('coid'));

      if (COIDParser::getType($coid)==COIDParser::COID_INVALID)
        throw new \Exception("Invalid COID: ".(string)$coid);

      $name = COIDParser::getName($coid);
      $version = COIDParser::getVersion($coid);
      $fullName = isset($version) ? $name.".".$version : $name;

      if (!file_exists($fullName.'.xml') || $input->getOption('force') == true) {
        // Create RDF configuration file
        $content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
          . "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n"
          . "   xmlns:phpmae=\"coid://phpmae.cloudobjects.io/\">\n"
          . "\n"
          . " <phpmae:ControllerClass rdf:about=\"".(string)$coid."\">\n"
          . " </phpmae:ControllerClass>\n"
          . "</rdf:RDF>";
        file_put_contents($fullName.'.xml', $content);
        $output->writeln("Written ".$fullName.".xml.");
      } else {
        // RDF configuration file already exists
        $output->writeln($fullName.".xml already exists.");
      }

      if (!file_exists($fullName.'.php') || $input->getOption('force') == true) {
        // Create PHP source file
        $content = "<?php\n"
          . "\n"
          . "use Silex\Application, Silex\ControllerProviderInterface;\n"
          . "\n"
          . "/**\n"
          . " * Implementation for ".(string)$coid."\n"
          . " */\n"
          . "class ".$name." implements ControllerProviderInterface {\n"
          . "\n"
          . "  public function connect(Application \$app) {\n"
          . "    \$controllers = \$app['controllers_factory'];\n"
          . "\n"
          . "    // Add methods here ...\n"
          . "\n"
          . "    return \$controllers;\n"
          . "  }\n"
          . "}";
        file_put_contents($fullName.'.php', $content);
        $output->writeln("Written ".$fullName.".php.");
      } else {
        $output->writeln($fullName.".php already exists.");
      }

      if ($input->getOption('confjob') == true) {
        $output->writeln("Calling cloudobjects ...");
        passthru("cloudobjects configuration-job:create ".$fullName.".xml");
      }
    }

}
