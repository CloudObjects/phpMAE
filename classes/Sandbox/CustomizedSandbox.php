<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE\Sandbox;

use PHPSandbox\PHPSandbox, PHPSandbox\SandboxWhitelistVisitor;
use PhpParser\NodeTraverser, PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Error as ParserError;

class CustomizedSandbox extends PHPSandbox {

    private static $globalSandbox;

    public function validate($code) {
        $this->preparsed_code = $this->disassemble($code);
        $factory = new ParserFactory;
        $parser = $factory->create(ParserFactory::PREFER_PHP7);
        try {
            $this->parsed_ast = $parser->parse($this->preparsed_code);
        } catch (ParserError $error) {
            $this->validationError("Could not parse sandboxed code!", Error::PARSER_ERROR, null, $this->preparsed_code, $error);
        }
        $prettyPrinter = new Standard();
        if(($this->allow_functions && $this->auto_whitelist_functions) ||
            ($this->allow_constants && $this->auto_whitelist_constants) ||
            ($this->allow_classes && $this->auto_whitelist_classes) ||
            ($this->allow_interfaces && $this->auto_whitelist_interfaces) ||
            ($this->allow_traits && $this->auto_whitelist_traits) ||
            ($this->allow_globals && $this->auto_whitelist_globals)){
            $traverser = new NodeTraverser;
            $whitelister = new SandboxWhitelistVisitor($this);
            $traverser->addVisitor($whitelister);
            $traverser->traverse($this->parsed_ast);
        }
        $traverser = new NodeTraverser;
        $validator = new CustomizedValidatorVisitor($this);
        $traverser->addVisitor($validator);
        $this->prepared_ast = $traverser->traverse($this->parsed_ast);
        $this->prepared_code = $prettyPrinter->prettyPrint($this->prepared_ast);
        return $this;
    }

    public function getGlobalSandbox() {
        if (!isset(static::$globalSandbox))
            static::$globalSandbox = new self;

        return static::$globalSandbox;
    }
}