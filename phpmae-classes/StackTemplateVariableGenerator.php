<?php

use CloudObjects\SDK\NodeReader, CloudObjects\SDK\ObjectRetriever;
use ML\IRI\IRI;

/**
 * Implementation for coid://phpmae.dev/StackTemplateVariableGenerator
 * Using interface coid://cloudobjects.io/DirectoryTemplateVariableGeneratorInterface
 */
class StackTemplateVariableGenerator implements DirectoryTemplateVariableGeneratorInterface {

    private $retriever;
    private $reader;

    public function __construct(ObjectRetriever $retriever) {
        $this->retriever = $retriever;
        $this->reader = new NodeReader([
            'prefixes' => [
                'co' => 'coid://cloudobjects.io/',
                'phpmae' => 'coid://phpmae.dev/'
            ]
        ]);
    }

    /**
     * Get a key-value array of template variables for an object.
     * @param string $coid The COID of the object.
     */
    public function getTemplateVariables($coid) {
        $coid = new IRI($coid);
        $object = $this->retriever->getObject($coid);
        if (!isset($object) || !$this->reader->hasType($object, 'phpmae:Stack'))
            return false;
        
        // Retrieve files
        $composerFile = json_decode($this->retriever->getAttachment($coid,
            $this->reader->getFirstValueString($object, 'phpmae:hasAttachedComposerFile')), true);
        $lockFile = json_decode($this->retriever->getAttachment($coid,
            $this->reader->getFirstValueString($object, 'phpmae:hasAttachedLockFile')), true);

        // Compile output
        $output = [
            'defined' => [],
            'actual' => []
        ];

        foreach ($composerFile['require'] as $name => $version) {
            $output['defined'][] = [
                'name' => $name,
                'version' => $version
            ];
        }

        foreach ($lockFile['packages'] as $package) {
            $output['actual'][] = [
                'name' => $package['name'],
                'version' => $package['version'],
                'defined' => isset($composerFile['require'][$package['name']])
            ];
        }

        return $output;
    }
}