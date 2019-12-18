<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use CloudObjects\PhpMAE\Sandbox\CustomizedSandbox;
use PHPSandbox\SandboxWhitelistVisitor, PHPSandbox\ValidatorVisitor;
use PhpParser\ParserFactory, PhpParser\NodeTraverser;
use ML\IRI\IRI;
use CloudObjects\SDK\COIDParser;
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
    $this->sandbox = new CustomizedSandbox;

    $this->whitelisted_interfaces = array(
      'Symfony\Component\EventDispatcher\EventSubscriberInterface',
      'Psr\Http\Message\RequestInterface',
      'Psr\Container\ContainerInterface'
    );
    $this->whitelisted_types = array(
      'ArrayObject', 'DateInterval', 'DateTime', 'DateTimeImmutable', 'DateTimeZone',
      'DOMElement', 'Exception',
      'SimpleXMLElement',
      'ML\IRI\IRI',
      'ML\JsonLD\JsonLD', 'ML\JsonLD\Node',
      'GuzzleHttp\Client',
      'GuzzleHttp\HandlerStack', 'GuzzleHttp\Middleware',
      'GuzzleHttp\Handler\CurlHandler',
      'GuzzleHttp\Subscriber\Oauth\Oauth1',
      'GuzzleHttp\Promise',
      'Dflydev\FigCookies\SetCookie',
      'CloudObjects\PhpMAE\ConfigLoader',
      'CloudObjects\PhpMAE\TwigTemplateFactory',
      'CloudObjects\SDK\ObjectRetriever',
      'CloudObjects\SDK\NodeReader',
      'CloudObjects\SDK\AccountGateway\AccountContext',
      'CloudObjects\SDK\AccountGateway\AAUIDParser',
      'CloudObjects\SDK\COIDParser',
      'CloudObjects\SDK\Common\CryptoHelper',
      'Webmozart\Assert\Assert'
    );
  }

  public function isWhitelisted($name) {
    return in_array($name, $this->whitelisted_types)
      || in_array($name, $this->whitelisted_interfaces);
  }

  private function initializeWhitelist($stack = ClassRepository::DEFAULT_STACK) {    
    // Generate whitelist based on alias names
    $interfaces = [];
    $types = [];
    foreach ($this->whitelisted_interfaces as $i) {
      $interfaces[] = (isset($this->aliases[$i]))
        ? strtolower($this->aliases[$i]) : strtolower($i);
    }
    foreach ($this->whitelisted_types as $t) {
      $types[] = (isset($this->aliases[$t]))
        ? strtolower($this->aliases[$t]) : strtolower($t);
    }

    // Load and apply stack
    $filename = __DIR__.'/../stacks/'.md5($stack).'/meta.json';
    if (!file_exists($filename))
      throw new PhpMAEException("The specified stack <".$stack."> is not installed.");
    $stackMeta = json_decode(file_get_contents($filename), true);
    if (isset($stackMeta['whitelisted_classes'])) {
      foreach ($stackMeta['whitelisted_classes'] as $t) {
        $types[] = (isset($this->aliases[$t]))
          ? strtolower($this->aliases[$t]) : strtolower($t);
      }
    }

    // Apply to sandbox
    $this->sandbox->whitelist(array(
      'interfaces' => $interfaces,
      'types' => $types,
      'classes' => $types
    ));
  }

  public function validate($sourceCode, IRI $coid, array $interfaceCoids = [],
      $stack = ClassRepository::DEFAULT_STACK) {
    
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

      if ($ast[0]->name != COIDParser::getName($coid))
        throw new PhpMAEException("The PHP classname (".$ast[0]->name.") doesn't match match the name segment of the COID (".COIDParser::getName($coid).").");

      $interfaces = array();
      foreach ($ast[0]->implements as $i) {
        $name = (string)$i;
        $interfaces[] = (isset($aliasMap[$name])) ? $aliasMap[$name] : $name;
      }

      // Check for any interfaces and whitelist them for validation
      foreach ($interfaceCoids as $coid) {
        if (!COIDParser::isValidCOID($coid)) continue;
        
        $name = COIDParser::getName($coid);
        $found = false;
        foreach ($interfaces as $i) {
          if ($i == $name) {
            $this->whitelisted_interfaces[] = $i;
            $found = true;
            break;
          }          
        }
        if (!$found)
          throw new PhpMAEException("Source code file must declare a class that implements <".$name.">.");
      }      

      // Allow self-references
      $this->whitelisted_types[] = strtolower($ast[0]->name);

      // Initialize whitelist
      $this->initializeWhitelist($stack);

      // Validate and prepare code in sandbox
      return $this->sandbox->prepare($sourceCode);

    } else {
      // Throw exeption if conditions are not met
      throw new PhpMAEException("Source code file must include exactly one class declaration and must not contain further side effects.");
    }
  }

  public function validateInterface($sourceCode, IRI $coid) {
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
    if (count($ast)==1 && get_class($ast[0])=='PhpParser\Node\Stmt\Interface_') {

      if ($ast[0]->name != COIDParser::getName($coid))
        throw new PhpMAEException("The PHP interface name (".$ast[0]->name.") doesn't match match the name segment of the COID (".COIDParser::getName($coid).").");

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
      throw new PhpMAEException("Source code file must include exactly one interface declaration and must not contain further side effects.");
    }
  }

  public static function isInvokableClass($class) {
    return method_exists($class, '__invoke');
  }

}
