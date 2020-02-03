<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use JsonRpc\Client;
use CloudObjects\PhpMAE\TestEnvironmentManager;

class ClassTestEnvRPCCommand extends AbstractObjectCommand {

    private $container;

    protected function getContainer() {
        if (!isset($this->container)) {
            $this->container = TestEnvironmentManager::getContainer();  
        }

        return $this->container;
    }

    protected function configure() {
        $this->setName('class:testenv-rpc')
            ->setDescription('Executes a JSON-RPC method call on a class in the current test environment.')
            ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.')
            ->addArgument('method', InputArgument::REQUIRED, 'The method name.')
            ->addArgument('parameters', InputArgument::IS_ARRAY, 'The parameters for the method.');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {      
        $container = $this->getContainer();
        if (!$container->has('testenv.client'))
            throw new \Exception("No test environment configured.");

        $this->parse($input->getArgument('coid'));
        $this->assertRDF();
        if (!in_array('coid://phpmae.cloudobjects.io/Class', $this->rdfTypes)
                && !in_array('coid://phpmae.cloudobjects.io/HTTPInvokableClass', $this->rdfTypes))
            throw new \Exception("Object does not have a valid class type.");
        $this->assertPHPExists();

        // Create JSON-RPC Client
        $client = new Client($container->get('testenv.url')
            .$this->coid->getHost().$this->coid->getPath());

        // Check parameters and load files
        $parameters = [];
        foreach ($input->getArgument('parameters') as $p) {
            if ($p[0] == '@')
                $parameters[] = @file_get_contents(substr($p, 1));
            else
                $parameters[] = $p;
        }

        // Make RPC Call
        if ($client->call($input->getArgument('method'), $parameters)
                && isset($client->result))
            $output->writeln(is_string($client->result) 
                ? $client->result
                : json_encode($client->result, JSON_PRETTY_PRINT));
        else
            $output->writeln("<error>RPC has failed!</error>");
    }

}
