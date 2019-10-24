<?php

use CloudObjects\SDK\NodeReader, CloudObjects\SDK\ObjectRetriever;
use ML\IRI\IRI;

/**
 * Implementation for coid://phpmae.cloudobjects.io/DirectoryTemplateVariableGenerator
 */
class DirectoryTemplateVariableGenerator implements DirectoryTemplateVariableGeneratorInterface {

    const CO_PUBLIC = 'coid://cloudobjects.io/Public';

    private $retriever;
    private $reader;

    public function __construct(ObjectRetriever $retriever) {
        $this->retriever = $retriever;
        $this->reader = new NodeReader([
            'prefixes' => [
                'co' => 'coid://cloudobjects.io/',
                'phpmae' => 'coid://phpmae.cloudobjects.io/'
            ]
        ]);
    }

    public function getTemplateVariables($coid) {
        $coid = new IRI($coid);
        $object = $this->retriever->getObject($coid);
        if (!isset($object))
            return false;
        
        $isInterface = $this->reader->hasType($object, 'phpmae:Interface');
        
        // Retrieve source code
        $sourceUrl = $isInterface
            ? $this->reader->getFirstValueString($object, 'phpmae:hasDefinitionFile')
            : $this->reader->getFirstValueString($object, 'phpmae:hasSourceFile');
        if (!$sourceUrl)
            return false;        
        $sourceCode = $this->retriever->getAttachment($coid, $sourceUrl);
        
        // Find method signatures and comment blocks using regular expression
        $matches = [];
        preg_match_all("/(?:\/\*\*((?:[\s\S](?!\/\*))*?)\*\/+\s*)?public\s+function\s+(\w+)\s*\((.+)\)/",
            $sourceCode, $matches);

        // The following groups are captured through RegExes:
        // 0 - complete definition block
        // 1 - comment string
        // 2 - method name
        // 3 - method parameters

        $methods = [];
        for ($i = 0; $i < count($matches[0]); $i++) {
            // Remove * and whitespace from from comment string.
            $commentString = trim(preg_replace('/\n\s+\*/', "\n", $matches[1][$i]));

            // Filter magic methods (incl. constructor), except __invoke
            if (substr($matches[2][$i], 0, 2) == '__' && $matches[2][$i] != '__invoke')
                continue;

            // List methods with parameters and comment
            $methods[] = [
                'name' => $matches[2][$i],
                'params' => trim($matches[3][$i]),
                'comment' => $commentString
            ];
        }

        $result = [ 'methods' => $methods ];

        // Check if public, in that case include source code
        if (!$isInterface
                && $this->reader->hasProperty($object, 'co:isVisibleTo')
                && $this->reader->getFirstValueIRI($object, 'co:isVisibleTo')
                    ->equals(self::CO_PUBLIC)
                && $this->reader->hasProperty($object, 'co:permitsUsageTo')
                && $this->reader->getFirstValueIRI($object, 'co:permitsUsageTo')
                    ->equals(self::CO_PUBLIC))            
            $result['public_source_code'] = $sourceCode;

        return $result;
    }

}