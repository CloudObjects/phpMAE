<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE\Sandbox;

use PhpParser\Node, PhpParser\NodeVisitorAbstract;
use PHPSandbox\PHPSandbox, PHPSandbox\Error;

class FunctionExecutorWrapperVisitor extends NodeVisitorAbstract {

    private $sandbox;

    public function __construct(PHPSandbox $sandbox) {
        $this->sandbox = $sandbox;
    }

    public function leaveNode(Node $node){
        if ($node instanceof Node\Expr\FuncCall) {
            if($node->name instanceof Node\Name) {
                $name = strtolower($node->name->toString());
                try {
                    $this->sandbox->checkFunc($name);
                } catch (\Exception $e) {                                      
                    return new Node\Expr\StaticCall(
                        new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\FunctionExecutor"), 'execute',
                        array_merge([ new Node\Scalar\String_($name) ], $node->args),
                        $node->getAttributes());
                }
            }
        }

        return null;
    }

}