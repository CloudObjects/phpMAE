<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use ML\IRI\IRI, ML\JsonLD\Node;
use CloudObjects\SDK\ObjectRetriever, CloudObjects\SDK\COIDParser;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class ClassRepository {

	private $options;
	private $classMap = array();

	private function loader($classname) {
		if (isset($this->classMap[$classname])) {
			include $this->classMap[$classname];
		}
	}

	public function __construct($options = array()) {
		// Merge options with defaults
		$this->options = array_merge(array(
			'cache_dir' => '',
			'uploads_dir' => '',
		), $options);

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

	/**
	 * Get a path on which custom files can be cached for a object.
	 * @param Node $object
	 */
	public function getCustomFilesCachePath(Node $object) {
		$path = $this->options['cache_dir'].DIRECTORY_SEPARATOR
			.strtoupper(md5($object->getId())).DIRECTORY_SEPARATOR
			.$object->getProperty(ObjectRetriever::REVISION_PROPERTY)->getValue();
		if (!is_dir($path))	mkdir($path, 0777, true);
		return $path;
	}

	public function storeUploadedFile(Node $object, $content) {
		$uri = new IRI($object->getId());
		$vars = $this->getURIVars($uri);

		// Fetch class description
		$revision = $object->getProperty(ObjectRetriever::REVISION_PROPERTY)->getValue();

		// Clear cache if cached version exists
		$cachedFilename = $vars['cache_path'].DIRECTORY_SEPARATOR.$revision.".php";
		if (file_exists($cachedFilename)) unlink($cachedFilename);

		// Store source
		file_put_contents($vars['upload_filename'], $content);
		return true;
	}

	/**
	 * Create an instance of a class.
	 * @param Node $object The object describing the class.
	 * @param ObjectRetriever $objectRetriever The repository, which is used to fetch the implementation.
	 * @param ErrorHandler $errorHandler An error handler; used to add information about the class for debugging.
	 */
	public function createInstance(Node $object, ObjectRetriever $objectRetriever, ErrorHandler $errorHandler) {
		// Check type
		if ((!TypeChecker::isController($object)) && (!TypeChecker::isProvider($object))) {
			throw new PhpMAEException("<".$object->getId()."> must have a valid type.");
		}

		$uri = new IRI($object->getId());
		$vars = $this->getURIVars($uri);
		if (!isset($this->classMap[$vars['php_classname']])) {
			// Get revision
			$revision = $object->getProperty(ObjectRetriever::REVISION_PROPERTY)->getValue();

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

				if (TypeChecker::isController($object)) {
					// Validate as controller
					$validator->validateAsController($sourceCode);
				} else
				if (TypeChecker::isProvider($object)) {
					// Validate as provider
					$validator->validateAsProvider($sourceCode);
				}

				// Add namespace declaration
				$sourceCode = str_replace("<?php", "<?php namespace ".$vars['php_namespace'].";", $sourceCode);
				$sourceCode = str_replace($vars['php_classname_local'].'::', '\\'.$vars['php_classname'].'::', $sourceCode);

				// Store
				if (!file_exists($vars['cache_path'])) mkdir($vars['cache_path'], 0777, true);
				file_put_contents($filename, $sourceCode);
			}

			$this->classMap[$vars['php_classname']] = $filename;
		}
		return new $vars['php_classname']();
	}

}
