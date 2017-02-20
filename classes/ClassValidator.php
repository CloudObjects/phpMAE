<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use PHPSandbox\PHPSandbox;
use PHPSandbox\SandboxWhitelistVisitor, PHPSandbox\ValidatorVisitor;
use PhpParser\ParserFactory, PhpParser\NodeTraverser;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

/**
 * Validates that classes fit the sandbox criteria.
 */
class ClassValidator {

  private $sandbox;
  private $whitelisted_interfaces;
  private $whitelisted_types;
  private $aliases;

  public function __construct() {
    $this->sandbox = new PHPSandbox();
    $this->sandbox->set_options(array(
      'allow_classes' => true,
      'allow_aliases' => true,
      'allow_closures' => true,
      'allow_casting' => true,
      'allow_error_suppressing' => true,
      'validate_constants' => false
    ));
    $this->sandbox->whitelist(array(
      'functions' => array(
        'abs', 'array_key_exists', 'array_diff', 'array_keys', 'array_merge', 'array_search',
        'array_slice', 'array_unshift', 'base64_decode', 'base64_encode', 'count',
        'date', 'explode', 'filter_var', 'get_class', 'gmdate',
        'hash', 'hash_hmac', 'http_build_query', 'idate', 'implode', 'is_a',
        'in_array', 'is_array', 'is_numeric', 'json_decode', 'json_encode',
        'ksort', 'md5',
        'parse_str', 'pathinfo', 'preg_match', 'preg_match_all', 'preg_replace', 'preg_split',
        'rand', 'rawurlencode', 'round', 'sha1', 'strlen',
        'str_replace', 'substr', 'str_split', 'strpos', 'strtolower', 'strtoupper',
        'strtr', 'time', 'trim', 'uksort', 'uniqid', 'usort', 'urlencode', 'var_dump',
        'promise\unwrap'
      )
    ));

    $this->whitelisted_interfaces = array(
      'Silex\Api\ControllerProviderInterface',
      'Silex\ServiceProviderInterface',
      'Symfony\Component\EventDispatcher\EventSubscriberInterface'
    );
    $this->whitelisted_types = array(
      'Silex\Application',
      'ArrayObject', 'DateTime', 'Exception',
      'ML\IRI\IRI',
      'GuzzleHttp\Client',
      'GuzzleHttp\HandlerStack',
      'GuzzleHttp\Subscriber\Oauth\Oauth1',
      'GuzzleHttp\Promise',
      'Symfony\Component\HttpFoundation\Cookie',
      'Symfony\Component\HttpFoundation\RedirectResponse',
      'Symfony\Component\HttpFoundation\Request',
      'Symfony\Component\HttpFoundation\Response',
      'Symfony\Component\DomCrawler\Crawler',
      'CloudObjects\SDK\NodeReader',
      'CloudObjects\SDK\AccountGateway\AccountContext',
      'CloudObjects\SDK\AccountGateway\AAUIDParser',
      'CloudObjects\SDK\COIDParser',
      'JWT',
      'Defuse\Crypto\Crypto'
    );
  }

  private function initializeWhitelist() {
    // Generate whitelist based on alias names
    $interfaces = array();
    $types = array();
    foreach ($this->whitelisted_interfaces as $i) {
      $interfaces[] = (isset($this->aliases[$i]))
        ? strtolower($this->aliases[$i]) : strtolower($i);
    }
    foreach ($this->whitelisted_types as $t) {
      $types[] = (isset($this->aliases[$t]))
        ? strtolower($this->aliases[$t]) : strtolower($t);
    }
    // Apply to sandbox
    $this->sandbox->whitelist(array(
      'interfaces' => $interfaces,
      'types' => $types,
      'classes' => $types
    ));
  }

  private function validate($sourceCode, $interface) {
    // Initialize parser and parse source code
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    $ast = $parser->parse($sourceCode);

    // Parse and dump use statements
    $aliasMap = array();
    while (get_class($ast[0])=='PhpParser\Node\Stmt\Use_') {
      foreach ($ast[0]->uses as $use) {
        $name = (string)$use->name;
        $aliasMap[$use->alias] = $name;
        $this->aliases[$name] = $use->alias;
      }
      array_shift($ast);
    }
    // Check for class definition and implemented interfaces
    if (count($ast)==1 && get_class($ast[0])=='PhpParser\Node\Stmt\Class_'
        && isset($ast[0]->implements)) {

      $interfaces = array();
      foreach ($ast[0]->implements as $i) {
        $name = (string)$i;
        $interfaces[] = (isset($aliasMap[$name])) ? $aliasMap[$name] : $name;
      }
      if (!in_array($interface, $interfaces)) {
        // Interface not implemented
        throw new PhpMAEException("Source code file must declare a class that implements <".$interface.">.");
      }

      // Allow self-references
      $this->whitelisted_types[] = strtolower($ast[0]->name);

      // Initialize whitelist
      $this->initializeWhitelist();

      // Apply whitelist visitor
      $traverser = new NodeTraverser;
      $traverser->addVisitor(new SandboxWhitelistVisitor($this->sandbox));
      $traverser->addVisitor(new ValidatorVisitor($this->sandbox));
      $traverser->traverse($ast);

      return;

    } else {
      // Throw exeption if conditions are not met
      throw new PhpMAEException("Source code file must include exactly one class declaration and must not contain further side effects.");
    }
  }

  public function validateAsController($sourceCode) {
    $this->validate($sourceCode, 'Silex\Api\ControllerProviderInterface');
  }

  public function validateAsProvider($sourceCode) {
    $this->validate($sourceCode, 'Silex\ServiceProviderInterface');
  }

}
