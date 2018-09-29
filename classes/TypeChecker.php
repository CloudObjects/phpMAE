<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use ML\JsonLD\Node;
use CloudObjects\SDK\NodeReader;

class TypeChecker {

  private static $reader;

  public static function isType(Node $object, $typeString) {
    if (!isset(self::$reader))
      self::$reader = new NodeReader([ 'prefixes' => [
        'phpmae' => 'coid://phpmae.cloudobjects.io/'
      ]]);
    
    return self::$reader->hasType($object, $typeString);
  }

  public static function isClass(Node $object) {
    return self::isType($object, 'phpmae:Class')
      || self::isType($object, 'phpmae:HTTPInvokableClass');
  }

}
