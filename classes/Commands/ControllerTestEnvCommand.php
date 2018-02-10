<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Cilex\Provider\Console\Command;
use ML\IRI\IRI;
use Guzzle\Http\Exception\BadResponseException;
use CloudObjects\SDK\COIDParser;
use CloudObjects\PhpMAE\ClassValidator;

class ControllerTestEnvCommand extends AbstractObjectCommand {

    protected function configure() {
      $this->setName('controller:testenv')
        ->setDescription('Uploads a controller into the current test environment.')
        ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.')
        ->addOption('config', null, InputOption::VALUE_OPTIONAL, 'Upload the local configuration of the controller to the test environment instead of retrieving it from CloudObjects.')
        ->addOption('watch', null, InputOption::VALUE_OPTIONAL, 'Keep watching for changes of the file and reupload automatically.', null);
    }

    private function upload(OutputInterface $output) {
      $app = $this->getContainer();

      try {
        $this->validator->validateAsController(file_get_contents($this->phpFileName));
        $app['testenv.client']->put('/uploads/'.$this->coid->getHost().$this->coid->getPath()
          .(COIDParser::getType($this->coid)==COIDParser::COID_UNVERSIONED ? '/Unversioned/' : '/')
          .'source.php', [
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
      $app = $this->getContainer();
      if (!isset($app['testenv.client'])) throw new \Exception("No test environment configured.");

      $this->parse($input->getArgument('coid'));
      $this->assertRDF();
      if (!in_array('coid://phpmae.cloudobjects.io/ControllerClass', $this->rdfTypes))
        throw new \Exception("Object is not a controller.");
      $this->assertPHPExists();

      // Print URL so developer can easily access it
      $output->writeln("<info>Test Environment Base URL for Controller:</info>");
      $suffix = (COIDParser::getType($this->coid) == COIDParser::COID_VERSIONED) ? "/" : "/Unversioned/";
      $output->writeln("➡️  ".$app['testenv.url']."run/".$this->coid->getHost().$this->coid->getPath().$suffix);
      $output->writeln("");

      if ($input->getOption('config') !== null) {
        // Upload configuration before uploading implementation
        $this->ensureFilenameInConfig($output);
        $app['testenv.client']->put('/uploads/'.$this->coid->getHost().$this->coid->getPath()
        .(COIDParser::getType($this->coid)==COIDParser::COID_UNVERSIONED ? '/Unversioned/' : '/')
        .'config.xml', [
          'body' => file_get_contents($this->xmlFileName)
        ]);
        $output->writeln('Configuration uploaded successfully!');
      }

      $this->validator = new ClassValidator;
      $this->upload($output);

      if ($input->getOption('watch') !== null) {
        $cmd = $this;
        $this->watchPHPFile($output, function() use ($cmd, $output) {
          $cmd->upload($output);
        });
      }
    }

}
