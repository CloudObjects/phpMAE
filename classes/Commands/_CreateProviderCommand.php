<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Cilex\Provider\Console\Command;
use CloudObjects\SDK\COIDParser;
use ML\IRI\IRI;

class CreateProviderCommand extends Command {

    protected function configure() {
      $this->setName('create:provider')
        ->setDescription('Create a new provider class for the phpMAE.')
        ->addArgument('uri', InputArgument::REQUIRED, 'Object URI');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
      $iri = new IRI($input->getArgument('uri'));
      if (COIDParser::getType($iri)!=COIDParser::COID_VERSIONED)
        throw new \Exception('Parameter "uri" must be a versioned CloudObjects Object Identifier.');

      $template = "<?php\n"
        . "\n"
        . "use Silex\Application, Silex\ServiceProviderInterface;\n"
        . "\n"
        . "/**\n"
        . " * Implementation of ".(string)$iri."\n"
        . " */\n"
        . "class ".COIDParser::getName($iri)." implements ServiceProviderInterface {\n"
        . "\n"
        . "  public function register(Application \$app) {\n"
        . "\n"
        . "  }\n"
        . "\n"
        . "  public function boot(Application \$app) {\n"
        . "\n"
        . "  }\n"
        . "}";


      $output->writeln($template);
    }

}
