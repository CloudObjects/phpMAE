<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Slim\Http\Response;

/**
 * The JsonRPCTransport writes JsonRPC output into a Slim response.
 */
class JsonRPCTransport {

    private $response;

    public function reply($data) {
        $this->response = (new Response(200))
            ->withHeader('Content-Type', 'application/json')
            ->write($data);
    }

    public function receive() {
        return "";
    }

    /**
     * Get the response
     */ 
    public function getResponse() {
        return $this->response;
    }
}
