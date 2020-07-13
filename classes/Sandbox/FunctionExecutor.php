<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE\Sandbox;

use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class FunctionExecutor {

    private static $trusted = false;
    private static $packageWhitelist = [ 'function_exists', 'extension_loaded',
        'defined', 'ini_get', 'is_callable', 'class_exists', 'get_called_class' ];

    public static function setTrusted(bool $trusted) {
        self::$trusted = $trusted;
    }

    public static function execute() {
        $call = func_get_args();
        if (self::$trusted == true || in_array($call[0], self::$packageWhitelist))
            return call_user_func_array($call[0], array_slice($call, 1));        
        else
            throw new PhpMAEException("A package attempted to call the non-whitelisted PHP function ".$call[0]."()!");
    }

}