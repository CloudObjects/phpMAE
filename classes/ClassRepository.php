<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use Psr\Container\ContainerInterface;
use ML\IRI\IRI, ML\JsonLD\Node;
use DI\Container, DI\FactoryInterface, Invoker\InvokerInterface;
use DI\Definition\Source\DefinitionArray, DI\Definition\Source\SourceChain;
use CloudObjects\SDK\ObjectRetriever, CloudObjects\SDK\COIDParser;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class ClassRepository {

	private $options;
	private $classMap = [];
	private $container;

	private function loader($classname) {
		if (isset($this->classMap[$classname])) {
			include $this->classMap[$classname];
		}
	}

	public function __construct(ContainerInterface $container) {
		$this->options = [
			'cache_dir' => $container->get('cache_dir') . '/classes',
			'uploads_dir' => $container->get('uploads_dir')
		];

		$this->container = $container;

		// Initialize autoloader
		spl_autoload_register(array($this, 'loader'));
	}

	private function getURIVars(IRI $uri) {
		$vars = array('uri_hash' => strtoupper(md5((string)$uri)));
		$vars['php_namespace'] = "CloudObjects\\PhpMAE\\Class_".$vars['uri_hash'];
		$vars['php_classname_local'] = (COIDParser::getType($uri)==COIDParser::COID_VERSIONED)
			? substr($uri->getPath(), 1, strrpos($uri->getPath(), '/')-1)
			: substr($uri->getPath(), 1);
		$vars['php_classname'] = $vars['php_namespace'].'\\'.$vars['php_classname_local'];
		$vars['cache_path'] = $this->options['cache_dir'].DIRECTORY_SEPARATOR.$vars['uri_hash'];
		$vars['upload_filename'] = $this->options['uploads_dir'].DIRECTORY_SEPARATOR.$vars['uri_hash'].'.php';
		if (!is_dir($this->options['uploads_dir'])) mkdir($this->options['uploads_dir'], 0777, true);
		return $vars;
	}

	public function coidToClassName(IRI $uri) {
		$vars = $this->getURIVars($uri);
		return $vars['php_classname'];
	}

	/**
	 * Get a path on which custom files can be cached for a object.
	 * @param Node $object
	 */
	public function getCustomFilesCachePath(Node $object) {
		$path = $this->options['cache_dir'].DIRECTORY_SEPARATOR
			.strtoupper(md5($object->getId())).DIRECTORY_SEPARATOR
			.($object->getProperty(ObjectRetriever::REVISION_PROPERTY)
				? $object->getProperty(ObjectRetriever::REVISION_PROPERTY)->getValue()
				: 'LocalConfig');
		if (!is_dir($path))	mkdir($path, 0777, true);
		return $path;
	}

	public function storeUploadedFile(Node $object, $content) {
		$uri = new IRI($object->getId());
		$vars = $this->getURIVars($uri);

		// Fetch class description
		$revision = $object->getProperty(ObjectRetriever::REVISION_PROPERTY)
			? $object->getProperty(ObjectRetriever::REVISION_PROPERTY)->getValue()
			: 'LocalConfig';

		// Clear cache if cached version exists
		$cachedFilename = $vars['cache_path'].DIRECTORY_SEPARATOR.$revision.".php";
		if (file_exists($cachedFilename)) unlink($cachedFilename);

		// Store source
		file_put_contents($vars['upload_filename'], $content);
		return true;
	}

	private function buildContainer($className, Node $object) {
		$autowiring = new DI\WhitelistReflectionBasedAutowiring;

		$sources = [
			new DefinitionArray($this->container->get(DI\DependencyInjector::class)
				->getDependencies($object), $autowiring),
			new DefinitionArray([
				Engine::SKEY => \DI\autowire($className)
			], $autowiring),
			$autowiring
		];

        $source = new SourceChain($sources);
        $source->setMutableDefinitionSource(new DefinitionArray([], $autowiring));

        // TODO: add compilation

		$container = new Container($source);
		$sandboxedContainer = new DI\SandboxedContainer($container);
		$container->set(ContainerInterface::class, $sandboxedContainer);
		$container->set(Container::class, $sandboxedContainer);
		$container->set(FactoryInterface::class, $sandboxedContainer);
        $container->set(InvokerInterface::class, $sandboxedContainer);
		return $sandboxedContainer;
	}

	/**
	 * Create an instance of a class. Returns a container that includes the class itself as Engine::SKEY
	 * as well as all dependencies specified by the class.
	 * 
	 * @param Node $object The object describing the class.
	 * @param ObjectRetriever $objectRetriever The repository, which is used to fetch the implementation.
	 * @param ErrorHandler $errorHandler An error handler; used to add information about the class for debugging.
	 */
	public function createInstance(Node $object, ObjectRetriever $objectRetriever, ErrorHandler $errorHandler) {
		// Check type
		if (!TypeChecker::isClass($object))
			throw new PhpMAEException("<".$object->getId()."> must have a valid type.");

		$uri = new IRI($object->getId());
		$vars = $this->getURIVars($uri);
		if (!isset($this->classMap[$vars['php_classname']])) {
			// Get revision
			$revision = $object->getProperty(ObjectRetriever::REVISION_PROPERTY)
				? $object->getProperty(ObjectRetriever::REVISION_PROPERTY)->getValue()
				: 'LocalConfig';

			// Build filename where cached version should exist
			$filename = $vars['cache_path'].DIRECTORY_SEPARATOR.$revision.".php";
			$errorHandler->addMapping($filename, $object);

			if (!file_exists($filename)) {
				// File does not exist -> check in uploads first
				if (file_exists($vars['upload_filename'])) {
					$sourceCode = file_get_contents($vars['upload_filename']);
				} else {
					// Not found in local uploads -> download source from CloudObjects
					$sourceUrl = $object->getProperty('coid://phpmae.cloudobjects.io/hasSourceFile');
					if (!$sourceUrl) throw new PhpMAEException("<".$object->getId()."> does not have an implementation source file.");
					if (get_class($sourceUrl)=='ML\JsonLD\Node')
						$sourceCode = $objectRetriever->getAttachment($uri, $sourceUrl->getId());
					else
						$sourceCode = $objectRetriever->getAttachment($uri, $sourceUrl->getValue());
				}

				// Run source code through validator to ensure sanity
				$validator = new ClassValidator;
				$validator->validate($sourceCode);

				// Add namespaces for dependencies
				$use = '';
				$classList = $this->container->get(DI\DependencyInjector::class)->getClassDependencyList($object);
				foreach ($classList as $cl)
					$use .= " use ".$this->coidToClassName($cl).";";

				// Add namespace declaration
				$sourceCode = str_replace("<?php", "<?php namespace ".$vars['php_namespace'].";".$use, $sourceCode);
				$sourceCode = str_replace($vars['php_classname_local'].'::', '\\'.$vars['php_classname'].'::', $sourceCode);

				// Store
				if (!file_exists($vars['cache_path'])) mkdir($vars['cache_path'], 0777, true);
				file_put_contents($filename, $sourceCode);
			}

			$this->classMap[$vars['php_classname']] = $filename;
		}

		return $this->buildContainer($vars['php_classname'], $object);
	}

}
