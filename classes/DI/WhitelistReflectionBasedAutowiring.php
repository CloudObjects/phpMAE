<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE\DI;

use DI\Definition\ObjectDefinition;
use DI\Definition\Source\DefinitionSource, DI\Definition\Source\Autowiring,
    DI\Definition\Source\ReflectionBasedAutowiring;

/**
 * This is an extension of ReflectionBasedAutowiring that only allows autowiring
 * for classes that have a definition. Used for sandboxing containers.
 */
class WhitelistReflectionBasedAutowiring extends ReflectionBasedAutowiring implements DefinitionSource, Autowiring {

    public function autowire(string $name, ObjectDefinition $definition = null) {
        return isset($definition) ? parent::autowire($name, $definition) : null;
    }

}
