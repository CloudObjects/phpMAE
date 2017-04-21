<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Cilex\Application;
use ML\IRI\IRI;
use CloudObjects\SDK\AccountGateway\AccountContext;

class CredentialManager {

    private static $context;
    
    private static function getFilename() {
        return getenv('HOME').DIRECTORY_SEPARATOR.'.cloudobjects';
    }

    public static function isConfigured() {
        return file_exists(self::getFilename());
    }

    public static function getAccountContext() {
        if (!self::$context) {
            if (file_exists(self::getFilename())) {
                $data = json_decode(file_get_contents(self::getFilename()), true);
                if (isset($data['aauid']) && isset($data['access_token']))
                    self::$context = new AccountContext(new IRI('aauid:'.$data['aauid']), $data['access_token']);
            }
        }

        return self::$context;
    }
}
