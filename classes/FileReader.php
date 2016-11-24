<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE;

use CloudObjects\SDK\ObjectRetriever, CloudObjects\PhpMAE\ClassRepository;
use ML\IRI\IRI;
use ML\JsonLD\Node;

/**
 * The FileReader provides access to files attached to the object.
 */
class FileReader {

  private $objectRetriever;
  private $classRepository;
  private $object;
  private $id;

  public function __construct(ObjectRetriever $objectRetriever,
      ClassRepository $classRepository, Node $object) {

    $this->objectRetriever = $objectRetriever;
    $this->classRepository = $classRepository;
    $this->object = $object;
    $this->id = new IRI($object->getId());
  }

  /**
   * Returns the raw content of a file.
   * @param array $filename Name of attached file
   */
  public function	getFileContent($filename) {
    $fullFilePath = $this->classRepository->getCustomFilesCachePath($this->object)
      .DIRECTORY_SEPARATOR.$filename;

    if (file_exists($fullFilePath)) {
      // Get cached file
      return file_get_contents($fullFilePath);
    } else {
      // Fetch requested file from CloudObjects and cache it
      $content = $this->objectRetriever->getAttachment($this->id, $filename);
      file_put_contents($fullFilePath, $content);
      return $content;
    }
  }

  /**
   * Parses a file as JSON and returns its PHP array representation.
   * @param array $filename Name of attached file
   */
  public function getJSON($filename) {
    $content = $this->getFileContent($filename);
    if ($content)
      return json_decode($content, true);
    else
      return null;
  }

}
