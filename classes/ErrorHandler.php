<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Slim\App;
use Slim\Http\Response;
use ML\JsonLD\Node;
use CloudObjects\SDK\ObjectRetriever;

class ErrorHandler {

    private $classMap;
    private $slim;

    public function __construct(App $slim) {
        $this->slim = $slim;
        ini_set("display_errors", 0);
    }

    public function addMapping($filename, Node $object) {
        $this->classMap[realpath($filename)] = $object;
    }

    public function getErrorResponse() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [ E_ERROR, E_COMPILE_ERROR ])
                && isset($this->classMap[$error['file']])) {
            
            $message = preg_replace("/CloudObjects\\\PhpMAE\\\Class_\w{32}\\\/", "", $error['message']);
            $object = $this->classMap[$error['file']];
            $response = (new Response(500))
                ->withHeader('Content-Type', 'text/plain')
                ->write("Error in implementation of <".$object->getId()."> at revision "
                . $object->getProperty(ObjectRetriever::REVISION_PROPERTY)->getValue()
                . ":\n"
                . "- [line ".$error['line']."] ".$message);
            $this->slim->respond($response);
        }
    }

}
