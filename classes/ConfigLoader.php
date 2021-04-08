<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use ML\IRI\IRI;
use CloudObjects\SDK\ObjectRetriever, CloudObjects\SDK\COIDParser,
    CloudObjects\SDK\NodeReader;

/**
 * The Config Loader helps phpMAE classes to load configuration
 * data from different CloudObjects objects.
 */
class ConfigLoader {

    private $validSources;
    private $retriever;
    private $reader;

    public function __construct(array $validSources, ObjectRetriever $retriever) {
        foreach ($validSources as $v) {
            if (!is_a($v, IRI::class))
                throw new \Exception("Invalid sources!");
        }
        $this->validSources = $validSources;
        $this->retriever = $retriever;
        $this->reader = new NodeReader([]);
    }

    /**
     * Get the value for a key, trying the sources in the order given by priorities.
     */
    public function get($key, $priorities, $default = null) {
        foreach ($priorities as $p) {
            if (isset($this->validSources[$p])) {
                $object = $this->retriever->getObject($this->validSources[$p]);
                if (!isset($object))
                    continue;
            } else
            if (substr($p, -10) == '.namespace' && isset($this->validSources[substr($p, 0, -10)])) {
                $object = $this->retriever->getObject(
                    COIDParser::getNamespaceCOID($this->validSources[substr($p, 0, -10)]));
                if (!isset($object))
                    continue;
            }
            if (!isset($object))
                continue;
            $value = $this->reader->getFirstValueString($object, $key);
            if (isset($value))
                return $value;
    }

        return $default; // not found
    }

}
