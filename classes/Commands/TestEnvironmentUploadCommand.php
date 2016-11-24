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
use ML\IRI\IRI;
use Guzzle\Http\Exception\BadResponseException;
use CloudObjects\SDK\COIDParser;

class TestEnvironmentUploadCommand extends Command {

    protected function configure() {
      $this->setName('testenv:upload')
        ->setDescription('Uploads a class file into the current test environment.')
        ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.')
        ->addArgument('filename', InputArgument::REQUIRED, 'The name of the file containing the class.')
        ->addOption('watch', null, InputOption::VALUE_OPTIONAL, 'Keep watching for changes of the file and reupload automatically.', false);
    }

    private function upload(IRI $coid, $filename, OutputInterface $output) {
      $app = $this->getContainer();

      try {
        $app['testenv.client']->put('/uploads/'.$coid->getHost().$coid->getPath()
          .(COIDParser::getType($coid)==COIDParser::COID_UNVERSIONED ? '/Unversioned/' : '/')
          .'source.php', [
            'body' => file_get_contents($filename)
          ]);
        $output->writeln('File uploaded successfully!');
      } catch (BadResponseException $e) {
        if ($e->getResponse()->getContentType()=='application/json') {
          $errorMessage = $e->getResponse()->json();
          $output->writeln('<error>'.$errorMessage['error_code'].':</error> '
            .$errorMessage['error_message']);
        } else
          $output->writeln('<error>Exception caught during upload:</error> '.(string)$e->getResponse());
      } catch (\Exception $e) {
        $output->writeln('<error>Exception caught during upload:</error> '.$e->getMessage());
      }
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
      $app = $this->getContainer();
      if (!isset($app['testenv.client'])) throw new \Exception("No test environment configured.");

      $coidString = $input->getArgument('coid');
      $coid = new IRI(substr($coidString, 0, 7)=='coid://'
        ? $coidString : 'coid://'.$coidString);

      $type = COIDParser::getType($coid);
      if ($type!=COIDParser::COID_VERSIONED && $type!=COIDParser::COID_UNVERSIONED) {
        $output->writeln('<error>Invalid COID: '.$coidString.'</error>');
        return;
      }

      $filename = $input->getArgument('filename');
      if (!file_exists($filename)) throw new \Exception("File not found.");
      $this->upload($coid, $filename, $output);

      if ($input->getOption('watch')==true) {
        $fileTime = filemtime($filename);
        $output->writeln('Watching for changes ...');
        while (true) {
          clearstatcache();
      		if (filemtime($filename)!=$fileTime) {
            // File has changed, upload again ...
            sleep(1);
      			$fileTime = filemtime($filename);
            $this->upload($coid, $filename, $output);
            $output->writeln('Watching for changes ...');
      		}
          sleep(0.5);
      	}
      }
    }

}
