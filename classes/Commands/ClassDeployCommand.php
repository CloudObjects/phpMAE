<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use CloudObjects\SDK\COIDParser;
use CloudObjects\PhpMAE\ClassValidator;

class ClassDeployCommand extends AbstractObjectCommand {

    protected function configure() {
      $this->setName('class:deploy')
        ->setDescription('Validates a class for the phpMAE and uploads it into CloudObjects. Updates the configuration if necessary.')
        ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->requireCloudObjectsCLI();
        
        $this->parse($input->getArgument('coid'));
        $this->assertRDF();
        if (!in_array('coid://phpmae.dev/Class', $this->rdfTypes)
                && !in_array('coid://phpmae.dev/HTTPInvokableClass', $this->rdfTypes))
          throw new \Exception("Object does not have a valid class type.");
        $this->assertPHPExists();

        // Running validator
        $validator = new ClassValidator;
        $validator->validate(file_get_contents($this->fullName.'.php'),
            $this->coid, $this->getAdditionalTypes());
        $output->writeln("Validated successfully, calling cloudobjects ...");

        passthru("cloudobjects attachment:put ".(string)$this->coid." ".$this->fullName.".php");

        // Updates configuration if necessary
        if ($this->ensureFilenameInConfig($output, false))
            $this->createConfigurationJob($output);

        // Print URL so developer can easily access it
        $output->writeln("");
        $visibility = isset($object['coid://cloudobjects.io/isVisibleTo'])
            ? $object['coid://cloudobjects.io/isVisibleTo'][0]['value']
            : 'coid://cloudobjects.io/Vendor';
        $path = $this->coid->getHost().$this->coid->getPath();
      
        switch ($visibility) {
            case "coid://cloudobjects.io/Private":
                $output->writeln("<info>Private URL for Class Execution:</info>");
                $output->writeln("➡️  http://YOUR_PHPMAE_INSTANCE/".$path);
                break;
            case "coid://cloudobjects.io/Public":
                $output->writeln("<info>Public URL for Class Execution:</info>");
                $output->writeln("➡️  https://phpmae.dev/".$path);
                break;
            case "coid://cloudobjects.io/Vendor":
                $output->writeln("<info>Authenticated URL for Class Execution:</info>");
                $output->writeln("➡️  https://".$this->coid->getHost().":SECRET@phpmae.dev/".$path);
                $output->writeln("");
                $output->writeln("To get value for SECRET:");
                $output->writeln("➡️  cloudobjects domain-providers:secret ".$this->coid->getHost()." phpmae.dev");
                break;
        }
        $output->writeln("");
    }

}
