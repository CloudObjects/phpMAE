<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Symfony\Component\HttpFoundation\Response;
use ML\JsonLD\Node;
use CloudObjects\SDK\ObjectRetriever;

class ErrorHandler {

    private $classMap;

    public function addMapping($filename, Node $object) {
        $this->classMap[realpath($filename)] = $object;
    }

    public function getErrorResponse() {
        $error = error_get_last();     
        if ($error !== null && $error['type'] === E_ERROR && isset($this->classMap[$error['file']])) {
            $object = $this->classMap[$error['file']];
            $response = new Response("Error in implementation of <".$object->getId()."> at revision "
                . $object->getProperty(ObjectRetriever::REVISION_PROPERTY)->getValue()
                . ":\n"
                . "- [line ".$error['line']."] ".$error['message'], 500,
                ["Content-Type" => "text/plain"]);
            $response->send();
        }
    }

}
