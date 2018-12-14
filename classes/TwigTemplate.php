<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

class TwigTemplate {

    private $key;
    private $environment;

    public function __construct($key, $content, $cachePath) {
        $this->key = $key;
        $this->environment = new \Twig_Environment(
            new \Twig_Loader_Array([ $key => $content ]),
            [ 'cache' => $cachePath ]
        );
    }

    public function render($context) {
        return $this->environment->render($this->key, $context);
    }
}