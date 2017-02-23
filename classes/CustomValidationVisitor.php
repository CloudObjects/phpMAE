<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use PHPSandbox\PHPSandbox;
use PhpParser\Node, PhpParser\NodeVisitorAbstract;

/**
 * Performs additional sandbox validation steps which are specific to phpMAE and
 * not implemented in a compatible way in the PHPSandbox library.
 */
class CustomValidationVisitor extends NodeVisitorAbstract {

    protected $sandbox;

    public function __construct(PHPSandbox $sandbox) {
        $this->sandbox = $sandbox;
    }

    public function leaveNode(Node $node) {
        if ($node instanceof Node\Expr\FuncCall) {
            if ($node->name instanceof Node\Expr\Variable) {
                $this->sandbox->validationError("Sandboxed code attempted to call a variable method!", 0, $node);
            }
        }
    }

}