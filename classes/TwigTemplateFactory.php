<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

class TwigTemplateFactory {

    private $cachePath;

    public function __construct($cachePath) {
        $this->cachePath = $cachePath;        
    }

    public function fromString($string, $id = null) {
        if ($id == null) $id = md5($string);
        return new TwigTemplate($id, $string, $this->cachePath);
    }
}