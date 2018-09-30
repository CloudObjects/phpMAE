<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Psr\Http\Server\MiddlewareInterface, Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface, Psr\Http\Message\ServerRequestInterface;

class EmptyMiddleware implements MiddlewareInterface {

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        return $handler->handle($request);
    }
}
