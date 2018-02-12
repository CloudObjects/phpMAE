<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use Defuse\Crypto\Key, Defuse\Crypto\Crypto;
use Symfony\Component\HttpFoundation\Request, Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\Cookie;

/**
 * The session identifies an individual over multiple requests and allows
 * storing encrypted information in a cookie.
 */
class Session implements \ArrayAccess {
    
    private $id;
    private $key;    
    private $data;

    public static function createFromRequestWithKey(Request $request, $keyString) {
        $session = new Session;

        if ($request->cookies->has('session')) {
            try {
                // attempt to restore session from encrypted data
                $key = Key::loadFromAsciiSafeString($keyString);
                $sessionContent = json_decode(Crypto::decrypt($request->cookies->get('session'), $key), true);
                if (!isset($sessionContent['id']) || !isset($sessionContent['data']))
                    throw new \Exception;
                $session->id = $sessionContent['id'];
                $session->data = $sessionContent['data'];
            } catch (\Exception $e) {
                // session could not be restored, create a new one anonymously
                $session->id = uniqid();
                $session->data = [];
            }
        } else {
            // no session found, create one
            $session->id = uniqid();
            $session->data = [];
        }

        $session->key = $keyString;

        return $session;
    }

    public function persistToResponse(Response $response) {
        $key = Key::loadFromAsciiSafeString($this->key);
        $sessionContent = json_encode([
            'id' => $this->id,
            'data' => $this->data
        ]);
        $response->headers->setCookie(new Cookie('session',
            Crypto::encrypt($sessionContent, $key),
            new \DateTime('1 hour')));
    }

    public function getId() {
        return $this->id;
    }

    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset) {
        return $this->data[$offset];
    }
    
    public function offsetSet($offset, $value) {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }
}