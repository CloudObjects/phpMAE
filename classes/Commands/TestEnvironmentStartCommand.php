<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Cilex\Provider\Console\Command;
use CloudObjects\PhpMAE\TestEnvironmentManager;
use CloudObjects\PhpMAE\CredentialManager;

class TestEnvironmentStartCommand extends Command {

    protected function configure() {
      $this->setName('testenv:start')
        ->setDescription('Starts a local test environment.')
        ->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Use this port for test environment.', 9000)
        ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Bind test environment to this host.', '127.0.0.1');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
      if (!CredentialManager::isConfigured())
        throw new \Exception("The 'cloudobjects' CLI tool must be installed and authorized.");

      $port = $input->getOption('port');
      $host = $input->getOption('host');

      TestEnvironmentManager::setTestEnvironment('http://localhost:'.$port.'/');
      $output->writeln('Local webserver started on port '.$port.'.');
      $phar = \Phar::running();
      if (!$phar || $phar == "") {
        // Run extracted version
        $dir = realpath(__DIR__."/../../web/");
        passthru('cd '.$dir.'; php -S '.$host.':'.$port.' index.php');
      } else {
        // Run from phar
        if (strpos($phar, '.phar') !== false) {
          // Run directly
          passthru('php -S '.$host.':'.$port.' '.$phar.'/web/index.php');
        } else {
          // Create copy with .phar extension because PHP might not recognize the file otherwise
          $pharCp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'phpmae.phar';
          copy(substr($phar, 7), $pharCp);
          passthru('php -S '.$host.':'.$port.' phar://'.$pharCp.'/web/index.php');
        }
      }
    }

}
