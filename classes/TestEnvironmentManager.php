<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Cilex\Application;
use GuzzleHttp\Client;

class TestEnvironmentManager {

    private static function getFilename() {
      return getenv('HOME').DIRECTORY_SEPARATOR.'.phpmae';
    }

    public static function configure(Application $app) {
      if (file_exists(self::getFilename())) {
        $data = json_decode(file_get_contents(self::getFilename()), true);
        if (isset($data['testenv_url'])) {
          $app['testenv.url'] = $data['testenv_url'];
          $app['testenv.client'] = function() use ($app) {
            return new Client([ 'base_uri' => $app['testenv.url'] ]);
          };
        }
      }
    }

    public static function setTestEnvironment($url) {
      file_put_contents(self::getFileName(), json_encode(array(
        'testenv_url' => $url
      )));
    }

    public static function unsetTestEnvironment() {
      file_put_contents(self::getFileName(), json_encode(array()));
    }

}
