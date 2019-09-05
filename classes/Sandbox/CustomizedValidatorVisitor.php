<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE\Sandbox;

use PhpParser\Node, PhpParser\NodeVisitorAbstract;
use PHPSandbox\PHPSandbox, PHPSandbox\ValidatorVisitor;

class CustomizedValidatorVisitor extends ValidatorVisitor {

    public function leaveNode(Node $node){
        if($node instanceof Node\Arg){
            return new Node\Expr\FuncCall(new Node\Name\FullyQualified(($node->value instanceof Node\Expr\Variable) ? 'PHPSandbox\\wrapByRef' : 'PHPSandbox\\wrap'), [$node, new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox')], $node->getAttributes());
        } else if($node instanceof Node\Stmt\InlineHTML){
            if(!$this->sandbox->allow_escaping){
                $this->sandbox->validationError("Sandboxed code attempted to escape to HTML!", Error::ESCAPE_ERROR, $node);
            }
        }else if($node instanceof Node\Expr\Cast){
            if(!$this->sandbox->allow_casting){
                $this->sandbox->validationError("Sandboxed code attempted to cast!", Error::CAST_ERROR, $node);
            }
            if($node instanceof Node\Expr\Cast\Int_){
                return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_intval', [new Node\Arg($node->expr)], $node->getAttributes());
            } else if($node instanceof Node\Expr\Cast\Double){
                return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_floatval', [new Node\Arg($node->expr)], $node->getAttributes());
            } else if($node instanceof Node\Expr\Cast\Bool_){
                return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_boolval', [new Node\Arg($node->expr)], $node->getAttributes());
            } else if($node instanceof Node\Expr\Cast\Array_){
                return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_arrayval', [new Node\Arg($node->expr)], $node->getAttributes());
            } else if($node instanceof Node\Expr\Cast\Object_){
                return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_objectval', [new Node\Arg($node->expr)], $node->getAttributes());
            }
        } else if($node instanceof Node\Expr\FuncCall){
            if($node->name instanceof Node\Name){
                $name = strtolower($node->name->toString());
                if(!$this->sandbox->checkFunc($name)){
                    $this->sandbox->validationError("Function failed custom validation!", Error::VALID_FUNC_ERROR, $node);
                }
                if($this->sandbox->isDefinedFunc($name)){
                    $args = $node->args;
                    array_unshift($args, new Node\Arg(new Node\Scalar\String_($name)));
                    return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), 'call_func', $args, $node->getAttributes());
                }
                if($this->sandbox->overwrite_defined_funcs && in_array($name, PHPSandbox::$defined_funcs)){
                    return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_' . $name, [new Node\Arg(new Node\Expr\FuncCall(new Node\Name([$name])))], $node->getAttributes());
                }
                if($this->sandbox->overwrite_sandboxed_string_funcs && in_array($name, PHPSandbox::$sandboxed_string_funcs)){
                    $args = $node->args;
                    return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_' . $name, $args, $node->getAttributes());
                }
                if($this->sandbox->overwrite_func_get_args && in_array($name, PHPSandbox::$arg_funcs)){
                    if($name == 'func_get_arg'){
                        $index = new Node\Arg(new Node\Scalar\LNumber(0));
                        if(isset($node->args[0]) && $node->args[0] instanceof Node\Arg){
                            $index = $node->args[0];
                        }
                        return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_' . $name, [new Node\Arg(new Node\Expr\FuncCall(new Node\Name(['func_get_args']))), $index], $node->getAttributes());
                    }
                    return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_' . $name, [new Node\Arg(new Node\Expr\FuncCall(new Node\Name(['func_get_args'])))], $node->getAttributes());
                }
            } else {
                return new Node\Expr\Ternary(
                    new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), 'check_func', [new Node\Arg($node->name)], $node->getAttributes()),
                    $node,
                    new Node\Expr\ConstFetch(new Node\Name('null'))
                );
            }
        } else if($node instanceof Node\Stmt\Function_){
            if(!$this->sandbox->allow_functions){
                $this->sandbox->validationError("Sandboxed code attempted to define function!", Error::DEFINE_FUNC_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('function')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'function');
            }
            if(!$node->name){
                $this->sandbox->validationError("Sandboxed code attempted to define unnamed function!", Error::DEFINE_FUNC_ERROR, $node, '');
            }
            if($this->sandbox->isDefinedFunc($node->name)){
                $this->sandbox->validationError("Sandboxed code attempted to redefine function!", Error::DEFINE_FUNC_ERROR, $node, $node->name);
            }
            if($node->byRef && !$this->sandbox->allow_references){
                $this->sandbox->validationError("Sandboxed code attempted to define function return by reference!", Error::BYREF_ERROR, $node);
            }
        } else if($node instanceof Node\Expr\Closure){
            if(!$this->sandbox->allow_closures){
                $this->sandbox->validationError("Sandboxed code attempted to create a closure!", Error::CLOSURE_ERROR, $node);
            }
        } else if($node instanceof Node\Stmt\Class_){
            if(!$this->sandbox->allow_classes){
                $this->sandbox->validationError("Sandboxed code attempted to define class!", Error::DEFINE_CLASS_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('class')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'class');
            }
            if(!$node->name){
                $this->sandbox->validationError("Sandboxed code attempted to define unnamed class!", Error::DEFINE_CLASS_ERROR, $node, '');
            }
            if(!$this->sandbox->checkClass($node->name)){
                $this->sandbox->validationError("Class failed custom validation!", Error::VALID_CLASS_ERROR, $node, $node->name);
            }
            if($node->extends instanceof Node\Name){
                if(!$this->sandbox->checkKeyword('extends')){
                    $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'extends');
                }
                if(!$node->extends->toString()){
                    $this->sandbox->validationError("Sandboxed code attempted to extend unnamed class!", Error::DEFINE_CLASS_ERROR, $node, '');
                }
                if(!$this->sandbox->checkClass($node->extends->toString(), true)){
                    $this->sandbox->validationError("Class extension failed custom validation!", Error::VALID_CLASS_ERROR, $node, $node->extends->toString());
                }
            }
            if(is_array($node->implements)){
                if(!$this->sandbox->checkKeyword('implements')){
                    $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'implements');
                }
                foreach($node->implements as $implement){
                    /**
                     * @var Node\Name   $implement
                     */
                    if(!$implement->toString()){
                        $this->sandbox->validationError("Sandboxed code attempted to implement unnamed interface!", Error::DEFINE_INTERFACE_ERROR, $node, '');
                    }
                    if(!$this->sandbox->checkInterface($implement->toString())){
                        $this->sandbox->validationError("Interface failed custom validation!", Error::VALID_INTERFACE_ERROR, $node, $implement->toString());
                    }
                }
            }
        } else if($node instanceof Node\Stmt\Interface_){
            if(!$this->sandbox->allow_interfaces){
                $this->sandbox->validationError("Sandboxed code attempted to define interface!", Error::DEFINE_INTERFACE_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('interface')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'interface');
            }
            if(!$node->name){
                $this->sandbox->validationError("Sandboxed code attempted to define unnamed interface!", Error::DEFINE_INTERFACE_ERROR, $node, '');
            }
            if(!$this->sandbox->checkInterface($node->name)){
                $this->sandbox->validationError("Interface failed custom validation!", Error::VALID_INTERFACE_ERROR, $node, $node->name);
            }
        } else if($node instanceof Node\Stmt\Trait_){
            if(!$this->sandbox->allow_traits){
                $this->sandbox->validationError("Sandboxed code attempted to define trait!", Error::DEFINE_TRAIT_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('trait')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'trait');
            }
            if(!$node->name){
                $this->sandbox->validationError("Sandboxed code attempted to define unnamed trait!", Error::DEFINE_TRAIT_ERROR, $node, '');
            }
            if(!$this->sandbox->checkTrait($node->name)){
                $this->sandbox->validationError("Trait failed custom validation!", Error::VALID_TRAIT_ERROR, $node, $node->name);
            }
        } else if($node instanceof Node\Stmt\TraitUse){
            if(!$this->sandbox->checkKeyword('use')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'use');
            }
            if(is_array($node->traits)){
                foreach($node->traits as $trait){
                    /**
                     * @var Node\Name   $trait
                     */
                    if(!$trait->toString()){
                        $this->sandbox->validationError("Sandboxed code attempted to use unnamed trait!", Error::DEFINE_TRAIT_ERROR, $node, '');
                    }
                    if(!$this->sandbox->checkTrait($trait->toString())){
                        $this->sandbox->validationError("Trait failed custom validation!", Error::VALID_TRAIT_ERROR, $node, $trait->toString());
                    }
                }
            }
        } else if($node instanceof Node\Expr\Yield_){
            if(!$this->sandbox->allow_generators){
                $this->sandbox->validationError("Sandboxed code attempted to create a generator!", Error::GENERATOR_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('yield')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'yield');
            }
        } else if($node instanceof Node\Stmt\Global_){
            if(!$this->sandbox->allow_globals){
                $this->sandbox->validationError("Sandboxed code attempted to use global keyword!", Error::GLOBALS_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('global')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'global');
            }
            foreach($node->vars as $var){
                /**
                 * @var Node\Expr\Variable    $var
                 */
                if($var instanceof Node\Expr\Variable){
                    if(!$this->sandbox->checkGlobal($var->name)){
                        $this->sandbox->validationError("Global failed custom validation!", Error::VALID_GLOBAL_ERROR, $node, $var->name);
                    }
                } else {
                    $this->sandbox->validationError("Sandboxed code attempted to pass non-variable to global keyword!", Error::DEFINE_GLOBAL_ERROR, $node);
                }
            }
        } else if($node instanceof Node\Expr\Variable){
            if(!is_string($node->name)){
                $this->sandbox->validationError("Sandboxed code attempted dynamically-named variable call!", Error::DYNAMIC_VAR_ERROR, $node);
            }
            if($node->name == $this->sandbox->name){
                $this->sandbox->validationError("Sandboxed code attempted to access the PHPSandbox instance!", Error::SANDBOX_ACCESS_ERROR, $node);
            }
            if(in_array($node->name, PHPSandbox::$superglobals)){
                if(!$this->sandbox->checkSuperglobal($node->name)){
                    $this->sandbox->validationError("Superglobal failed custom validation!", Error::VALID_SUPERGLOBAL_ERROR, $node, $node->name);
                }
                if($this->sandbox->overwrite_superglobals){
                    return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_get_superglobal', [new Node\Arg(new Node\Scalar\String_($node->name))], $node->getAttributes());
                }
            } else {
                if(!$this->sandbox->checkVar($node->name)){
                    $this->sandbox->validationError("Variable failed custom validation!", Error::VALID_VAR_ERROR, $node, $node->name);
                }
            }
        } else if($node instanceof Node\Stmt\StaticVar){
            if(!$this->sandbox->allow_static_variables){
                $this->sandbox->validationError("Sandboxed code attempted to create static variable!", Error::STATIC_VAR_ERROR, $node);
            }
            if(!is_string($node->name)){
                $this->sandbox->validationError("Sandboxed code attempted dynamically-named static variable call!", Error::DYNAMIC_STATIC_VAR_ERROR, $node);
            }
            if(!$this->sandbox->checkVar($node->name)){
                $this->sandbox->validationError("Variable failed custom validation!", Error::VALID_VAR_ERROR, $node, $node->name);
            }
            if($node->default instanceof Node\Expr\New_){
                $node->default = $node->default->args[0];
            }
        } else if($node instanceof Node\Stmt\Const_){
            $this->sandbox->validationError("Sandboxed code cannot use const keyword in the global scope!", Error::GLOBAL_CONST_ERROR, $node);
        } else if($node instanceof Node\Expr\ConstFetch){
            if(!$node->name instanceof Node\Name){
                $this->sandbox->validationError("Sandboxed code attempted dynamically-named constant call!", Error::DYNAMIC_CONST_ERROR, $node);
            }
            if(!$this->sandbox->checkConst($node->name->toString())){
                $this->sandbox->validationError("Constant failed custom validation!", Error::VALID_CONST_ERROR, $node, $node->name->toString());
            }
        } else if($node instanceof Node\Expr\ClassConstFetch || $node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\StaticPropertyFetch){
            $class = $node->class;
            if(!$class instanceof Node\Name){
                $this->sandbox->validationError("Sandboxed code attempted dynamically-named class call!", Error::DYNAMIC_CLASS_ERROR, $node);
            }
            if($this->sandbox->isDefinedClass($class)){
                $node->class = new Node\Name($this->sandbox->getDefinedClass($class));
            }
            /**
             * @var Node\Name    $class
             */
            if(!$this->sandbox->checkClass($class->toString())){
                $this->sandbox->validationError("Class constant failed custom validation!", Error::VALID_CLASS_ERROR, $node, $class->toString());
            }
            return $node;
        } else if($node instanceof Node\Param && $node->type instanceof Node\Name){
            $class = $node->type->toString();
            if($this->sandbox->isDefinedClass($class)){
                $node->type = new Node\Name($this->sandbox->getDefinedClass($class));
            }
            return $node;
        } else if($node instanceof Node\Expr\New_){
            if(!$this->sandbox->allow_objects){
                $this->sandbox->validationError("Sandboxed code attempted to create object!", Error::CREATE_OBJECT_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('new')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'new');
            }
            if(!$node->class instanceof Node\Name){
                $this->sandbox->validationError("Sandboxed code attempted dynamically-named class call!", Error::DYNAMIC_CLASS_ERROR, $node);
            }
            $class = $node->class->toString();
            if($this->sandbox->isDefinedClass($class)){
                $node->class = new Node\Name($this->sandbox->getDefinedClass($class));
            }
            $this->sandbox->checkType($class);
            return $node;
        } else if($node instanceof Node\Expr\ErrorSuppress){
            if(!$this->sandbox->allow_error_suppressing){
                $this->sandbox->validationError("Sandboxed code attempted to suppress error!", Error::ERROR_SUPPRESS_ERROR, $node);
            }
        } else if($node instanceof Node\Expr\AssignRef){
            if(!$this->sandbox->allow_references){
                $this->sandbox->validationError("Sandboxed code attempted to assign by reference!", Error::BYREF_ERROR, $node);
            }
        } else if($node instanceof Node\Stmt\HaltCompiler){
            if(!$this->sandbox->allow_halting){
                $this->sandbox->validationError("Sandboxed code attempted to halt compiler!", Error::HALT_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('halt')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'halt');
            }
        } else if($node instanceof Node\Stmt\Namespace_){
            if(!$this->sandbox->allow_namespaces){
                $this->sandbox->validationError("Sandboxed code attempted to define namespace!", Error::DEFINE_NAMESPACE_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('namespace')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'namespace');
            }
            if($node->name instanceof Node\Name){
                $namespace = $node->name->toString();
                $this->sandbox->checkNamespace($namespace);
                if(!$this->sandbox->isDefinedNamespace($namespace)){
                    $this->sandbox->defineNamespace($namespace);
                }
            } else {
                $this->sandbox->validationError("Sandboxed code attempted use invalid namespace!", Error::DEFINE_NAMESPACE_ERROR, $node);
            }
            return $node->stmts;
        } else if($node instanceof Node\Stmt\Use_){
            if(!$this->sandbox->allow_aliases){
                $this->sandbox->validationError("Sandboxed code attempted to use namespace and/or alias!", Error::DEFINE_ALIAS_ERROR, $node);
            }
            if(!$this->sandbox->checkKeyword('use')){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'use');
            }
            foreach($node->uses as $use){
                /**
                 * @var Node\Stmt\UseUse    $use
                 */
                if($use instanceof Node\Stmt\UseUse && $use->name instanceof Node\Name && (is_string($use->alias) || is_null($use->alias))){
                    $this->sandbox->checkAlias($use->name->toString());
                    if($use->alias){
                        if(!$this->sandbox->checkKeyword('as')){
                            $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, 'as');
                        }
                    }
                    $this->sandbox->defineAlias($use->name->toString(), $use->alias);
                } else {
                    $this->sandbox->validationError("Sandboxed code attempted use invalid namespace or alias!", Error::DEFINE_ALIAS_ERROR, $node);
                }
            }
            return false;
        } else if($node instanceof Node\Expr\ShellExec){
            if($this->sandbox->isDefinedFunc('shell_exec')){
                $args = [
                    new Node\Arg(new Node\Scalar\String_('shell_exec')),
                    new Node\Arg(new Node\Scalar\String_(implode('', $node->parts)))
                ];
                return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), 'call_func', $args, $node->getAttributes());
            }
            if($this->sandbox->hasWhitelistedFuncs()){
                if(!$this->sandbox->isWhitelistedFunc('shell_exec')){
                    $this->sandbox->validationError("Sandboxed code attempted to use shell execution backticks when the shell_exec function is not whitelisted!", Error::BACKTICKS_ERROR, $node);
                }
            } else if($this->sandbox->hasBlacklistedFuncs() && $this->sandbox->isBlacklistedFunc('shell_exec')){
                $this->sandbox->validationError("Sandboxed code attempted to use shell execution backticks when the shell_exec function is blacklisted!", Error::BACKTICKS_ERROR, $node);
            }
            if(!$this->sandbox->allow_backticks){
                $this->sandbox->validationError("Sandboxed code attempted to use shell execution backticks!", Error::BACKTICKS_ERROR, $node);
            }
        } else if($name = $this->isMagicConst($node)){
            if(!$this->sandbox->checkMagicConst($name)){
                $this->sandbox->validationError("Magic constant failed custom validation!", Error::VALID_MAGIC_CONST_ERROR, $node, $name);
            }
            if($this->sandbox->isDefinedMagicConst($name)){
                return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_get_magic_const', [new Node\Arg(new Node\Scalar\String_($name))], $node->getAttributes());
            }
        } else if($name = $this->isKeyword($node)){
            if(!$this->sandbox->checkKeyword($name)){
                $this->sandbox->validationError("Keyword failed custom validation!", Error::VALID_KEYWORD_ERROR, $node, $name);
            }
            if($node instanceof Node\Expr\Include_ && !$this->sandbox->allow_includes){
                $this->sandbox->validationError("Sandboxed code attempted to include files!", Error::INCLUDE_ERROR, $node, $name);
            } else if($node instanceof Node\Expr\Include_ &&
                (
                    ($node->type == Node\Expr\Include_::TYPE_INCLUDE && $this->sandbox->isDefinedFunc('include'))
                    || ($node->type == Node\Expr\Include_::TYPE_INCLUDE_ONCE && $this->sandbox->isDefinedFunc('include_once'))
                    || ($node->type == Node\Expr\Include_::TYPE_REQUIRE && $this->sandbox->isDefinedFunc('require'))
                    || ($node->type == Node\Expr\Include_::TYPE_REQUIRE_ONCE && $this->sandbox->isDefinedFunc('require_once'))
                )){
                return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), 'call_func', [new Node\Arg(new Node\Scalar\String_($name)), new Node\Arg($node->expr)], $node->getAttributes());
            } else if($node instanceof Node\Expr\Include_ && $this->sandbox->sandbox_includes){
                switch($node->type){
                    case Node\Expr\Include_::TYPE_INCLUDE_ONCE:
                        return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_include_once', [new Node\Arg($node->expr)], $node->getAttributes());
                        break;
                    case Node\Expr\Include_::TYPE_REQUIRE:
                        return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_require', [new Node\Arg($node->expr)], $node->getAttributes());
                        break;
                    case Node\Expr\Include_::TYPE_REQUIRE_ONCE:
                        return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_require_once', [new Node\Arg($node->expr)], $node->getAttributes());
                        break;
                    case Node\Expr\Include_::TYPE_INCLUDE:
                    default:
                        return new Node\Expr\MethodCall(new Node\Expr\StaticCall(new Node\Name\FullyQualified("CloudObjects\\PhpMAE\\Sandbox\\CustomizedSandbox"), 'getGlobalSandbox'), '_include', [new Node\Arg($node->expr)], $node->getAttributes());
                        break;
                }
            }
        } else if($name = $this->isOperator($node)){
            if(!$this->sandbox->checkOperator($name)){
                $this->sandbox->validationError("Operator failed custom validation!", Error::VALID_OPERATOR_ERROR, $node, $name);
            }
        } else if($name = $this->isPrimitive($node)){
            if(!$this->sandbox->checkPrimitive($name)){
                $this->sandbox->validationError("Primitive failed custom validation!", Error::VALID_PRIMITIVE_ERROR, $node, $name);
            }
        }
        return null;
    }

}