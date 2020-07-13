<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ML\IRI\IRI;
use Guzzle\Http\Exception\BadResponseException;
use CloudObjects\SDK\COIDParser;
use CloudObjects\PhpMAE\ClassValidator, CloudObjects\PhpMAE\TestEnvironmentManager;

class ClassTestEnvCommand extends AbstractObjectCommand {

    private $container;

    protected function getContainer() {
        if (!isset($this->container)) {
            $this->container = TestEnvironmentManager::getContainer();  
        }

        return $this->container;
    }

    protected function configure() {
        $this->setName('class:testenv')
            ->setDescription('Uploads a class into the current test environment.')
            ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.')
            ->addOption('config', null, InputOption::VALUE_NONE, 'Upload the local configuration of the class to the test environment instead of retrieving it from CloudObjects.')
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Keep watching for changes of the file and reupload automatically.');
    }

    private function upload(OutputInterface $output) {
        try {
            $this->validator->validate(file_get_contents($this->phpFileName),
                $this->coid, $this->getAdditionalTypes());
            $this->getContainer()->get('testenv.client')->put('/uploadTestenv?type=source&coid='.urlencode((string)$this->coid), [
                'body' => file_get_contents($this->phpFileName)
            ]);
            $output->writeln('File uploaded successfully!');
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getContentType()=='application/json') {
                $errorMessage = $e->getResponse()->json();
                $output->writeln('<error>'.$errorMessage['error_code'].':</error> '
                    .$errorMessage['error_message']);
            } else
                $output->writeln('<error>'.get_class($e).'</error> '.(string)$e->getResponse());
        } catch (\Exception $e) {
            $output->writeln('<error>'.get_class($e).'</error> '.$e->getMessage());
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->requireCloudObjectsCLI();

        $container = $this->getContainer();
        if (!$container->has('testenv.client'))
            throw new \Exception("No test environment configured.");

        $this->parse($input->getArgument('coid'));
        $this->assertRDF();
        if (!in_array('coid://phpmae.cloudobjects.io/Class', $this->rdfTypes)
                && !in_array('coid://phpmae.cloudobjects.io/HTTPInvokableClass', $this->rdfTypes))
            throw new \Exception("Object does not have a valid class type.");
        $this->assertPHPExists();

        // Print URL so developer can easily access it
        $output->writeln("<info>Test Environment Base URL for Class Execution:</info>");
        $output->writeln("➡️  ".$container->get('testenv.url').$this->coid->getHost()
           .$this->coid->getPath());
        $output->writeln("");

        if ($input->getOption('config')) {
            // Upload configuration before uploading implementation
            $this->ensureFilenameInConfig($output);
            $container->get('testenv.client')->put('/uploadTestenv?type=config&coid='.urlencode((string)$this->coid), [
                'body' => file_get_contents($this->xmlFileName)
            ]);
            $output->writeln('Configuration uploaded successfully!');
        }

        $this->validator = new ClassValidator;
        $this->upload($output);

        if ($input->getOption('watch')) {
            $cmd = $this;
            $this->watchPHPFile($output, function() use ($cmd, $output) {
                $cmd->upload($output);
            });
        }
    }

}
