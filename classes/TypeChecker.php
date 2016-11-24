<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use ML\JsonLD\Node;

class TypeChecker {

  public static function isType(Node $object, $typeString) {
    $types = $object->getType();
    if (!is_array($types)) $types = array($types);
    foreach ($types as $t) {
      if (is_a($t, 'ML\JsonLD\Node') && $t->getId()==$typeString) return true;
    }
    return false;
  }

  public static function isController(Node $object) {
    return self::isType($object, 'coid://phpmae.cloudobjects.io/ControllerClass');
  }

  public static function isProvider(Node $object) {
    return self::isType($object, 'coid://phpmae.cloudobjects.io/ServiceProviderClass');
  }

}
