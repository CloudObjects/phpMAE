<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Commands;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use ML\IRI\IRI;
use CloudObjects\SDK\COIDParser;
use CloudObjects\PhpMAE\CredentialManager, CloudObjects\PhpMAE\ClassValidator;
use CloudObjects\PhpMAE\Exceptions\PhpMAEException;

class ClassCreateCommand extends Command {

    protected function configure() {
        $this->setName('class:create')
            ->setDescription('Create a new class for the phpMAE.')
            ->addArgument('coid', InputArgument::REQUIRED, 'The COID of the object.')
            ->addOption('http-invokable', 'hi', InputOption::VALUE_NONE, 'Makes the class HTTP-invokable.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces new object creation and replaces existing files.')
            ->addOption('public', null, InputOption::VALUE_NONE, 'Marks new object as public.')
            ->addOption('confjob', null, InputOption::VALUE_NONE, 'Calls "cloudobjects" to create a configuration job for the new class.')
            ->addOption('autowire', null, InputOption::VALUE_REQUIRED, 'Creates a constructor that autowires a PHP dependency.', null)
            ->addOption('implements', null, InputOption::VALUE_REQUIRED, 'Make this class an implementation of the phpMAE interface with the specified COID.', null);
    }

    private function getAndValidateInterfaceConfig($implements) {
        // Check interface COID
        $interfaceCoid = COIDParser::fromString($implements);
        if (COIDParser::getType($interfaceCoid) != COIDParser::COID_VERSIONED
                && COIDParser::getType($interfaceCoid) != COIDParser::COID_UNVERSIONED)
            throw new \Exception("Invalid Interface COID: ".(string)$interfaceCoid);
      
        // Retrieve interface configuration
        $implements = (string)$interfaceCoid;
        $interfaceConfig = shell_exec("cloudobjects get ".$implements);
        if (!isset($interfaceConfig))
            throw new \Exception("Could not retrieve interface configuration.");

        // Parse and validate interface configuration
        $parser = \ARC2::getRDFXMLParser();
        $parser->parse('', $interfaceConfig);
        $index = $parser->getSimpleIndex(false);
        if (!isset($index) || !isset($index[$implements])
                || !isset($index[$implements]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type']))
            throw new \Exception("<".$implements."> is not a valid CloudObjects object.");
      
        $isInterface = false;
        foreach ($index[$implements]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'] as $property => $values) {
            if ($values['value'] == 'coid://phpmae.dev/Interface')
                $isInterface = true;
        }

        if (!$isInterface || !isset($index[$implements]['coid://phpmae.dev/hasDefinitionFile']))
            throw new \Exception("<".$implements."> is not a valid phpMAE interface.");
            
        return [
            'classname' => COIDParser::getName($interfaceCoid),
            'filename' => basename($index[$implements]['coid://phpmae.dev/hasDefinitionFile'][0]['value']),
            'coid' => (string)$interfaceCoid,
        ];
    }

    private function getAndParseInterfaceCode($interface, $filename) {
        // Retrieve interface code
        $interfaceCode = shell_exec("cloudobjects attachment:get ".$interface
            ." ".$filename);
      
        // Run through validator
        $validator = new ClassValidator;
        $validator->validateInterface($interfaceCode, new IRI($interface));

        // Find use statements
        $matches = [];
        preg_match_all("/use\s+(.+);/", $interfaceCode, $matches);
        $use = $matches[1];

        // Parse method definitions
        // (this is the same algorithm as the DirectoryTemplateVariableGenerator,
        // but less filtering on comment string)
        $matches = [];
        preg_match_all("/(?:\/\*\*((?:[\s\S](?!\/\*))*?)\*\/+\s*)?public\s+function\s+(\w+)\s*\((.+)\)/",
            $interfaceCode, $matches);

        // The following groups are captured through RegExes:
        // 0 - complete definition block
        // 1 - comment string
        // 2 - method name
        // 3 - method parameters

        $methods = [];
        for ($i = 0; $i < count($matches[0]); $i++) {
            // List methods with parameters and comment
            $methods[] = [
                'name' => $matches[2][$i],
                'params' => trim($matches[3][$i]),
                'comment' => trim($matches[1][$i])
            ];
        }

        return [
            'methods' => $methods,
            'use' => $use
        ];
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        if (!CredentialManager::isConfigured())
            throw new \Exception("The 'cloudobjects' CLI tool must be installed and authorized.");

        $coid = COIDParser::fromString($input->getArgument('coid'));

        if (COIDParser::getType($coid) != COIDParser::COID_VERSIONED
                && COIDParser::getType($coid) != COIDParser::COID_UNVERSIONED)
            throw new \Exception("Invalid COID: ".(string)$coid);

        $name = COIDParser::getName($coid);
        $version = COIDParser::getVersion($coid);
        $fullName = isset($version) ? $name.".".$version : $name;
        $invokable = $input->getOption('http-invokable');

        if ($input->getOption('implements') != null) {
            if ($invokable)
                throw new \Exception("The 'implements' option cannot be used with 'http-invokable'.");

            $implements = $input->getOption('implements');
            $output->writeln("Fetching configuration for ".$implements." ...");
            $interfaceData = $this->getAndValidateInterfaceConfig($implements);
        }

        if (!file_exists($fullName.'.xml') || $input->getOption('force')) {
            // Create RDF configuration file
            $content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\"\n"
                . "   xmlns:co=\"coid://cloudobjects.io/\"\n"
                . "   xmlns:phpmae=\"coid://phpmae.dev/\">\n"
                . "\n"
                . " <phpmae:".($invokable ? "HTTPInvokable" : "")."Class rdf:about=\"".(string)$coid."\">\n";

            // Mark as public if option was defined
            if ($input->getOption('public'))
                $content .= "  <co:isVisibleTo rdf:resource=\"coid://cloudobjects.io/Public\" />\n"
                . "  <co:permitsUsageTo rdf:resource=\"coid://cloudobjects.io/Public\" />\n";
            else
                $content .= "  <co:isVisibleTo rdf:resource=\"coid://cloudobjects.io/Vendor\" />\n";
                
            if (isset($implements))
                $content .= "  <rdf:type rdf:resource=\"".$interfaceData['coid']."\" />\n";
            $content .=" </phpmae:".($invokable ? "HTTPInvokable" : "")."Class>\n"
          . "</rdf:RDF>";
            file_put_contents($fullName.'.xml', $content);
            $output->writeln("Written ".$fullName.".xml.");
        } else {
            // RDF configuration file already exists
            $output->writeln($fullName.".xml already exists.");
        }

        if (!file_exists($fullName.'.php') || $input->getOption('force') !== false) {
            $useStatements = [];
            $classVariables = [];
            $constructor = '';

            if ($input->getOption('autowire') != null) {
                $fullyQualifiedClassname = $input->getOption('autowire');
                $validator = new ClassValidator;
                if (!$validator->isWhitelisted($fullyQualifiedClassname))
                    throw new PhpMAEException('Cannot autowire non-whitelisted type <'.$fullyQualifiedClassname.'>.');
                $useStatements[] = $fullyQualifiedClassname;
                $className = substr($fullyQualifiedClassname, strrpos($fullyQualifiedClassname, '\\') + 1);
                $variableName = strtolower($className[0]).substr($className, 1);
                $classVariables[] = $variableName;
                $constructor = "    public function __construct(".$className." \$".$variableName.") {\n"
                    . "        \$this->".$variableName." = \$".$variableName.";\n"
                    . "    }\n\n";
            }

            if (isset($implements)) {
                // Retrieve interface code
                $output->writeln("Fetching code for ".$interfaceData['coid']." ...");
                $parsedInterface = $this->getAndParseInterfaceCode($interfaceData['coid'], $interfaceData['filename']);
                // Add use statements
                foreach ($parsedInterface['use'] as $u)
                    $useStatements[] = $u;
            }

            // Create PHP source file
            $content = "<?php\n"
                . "\n";
            foreach ($useStatements as $u)
                $content .= "use ".$u.";\n";
            if (count($useStatements) > 0)
                $content .= "\n";

            $content .= "/**\n"
                . " * Implementation for ".(string)$coid."\n"
                . (isset($implements) ? " * Using interface ".$interfaceData['coid']."\n" : "")
                . " */\n"
                . "class ".$name." ".(isset($implements) ? "implements ".$interfaceData['classname']." " : "")
                . "{\n"
                . "\n";
            foreach ($classVariables as $v)
                $content .= "    private \$".$v.";\n";
            if (count($classVariables) > 0)
                $content .= "\n"
                    . $constructor;
        
            if (isset($implements)) {
                // Build PHP template with interface methods
                foreach ($parsedInterface['methods'] as $m) {
                    $content .= ($m['comment'] != "" ? "    /**\n     ".$m['comment']."\n     */\n" : "")
                        . "    public function ".$m['name']."(".$m['params'].") {\n"
                        . "        // TODO: Implement this\n"
                        . "    }\n\n";
                }
            } else {
                // Build PHP template for standard or HTTP-invokable class          
                $content .= ($invokable
                    ? "    public function __invoke(\$args) {\n        // TODO: Add your code here\n    }\n"
                    : "    // TODO: Add methods here ...\n")
                    . "\n";
            }

            $content .= "}";
        
            file_put_contents($fullName.'.php', $content);
            $output->writeln("Written ".$fullName.".php.");
        } else {
            $output->writeln($fullName.".php already exists.");
        }

        if ($input->getOption('confjob')) {
            $output->writeln("Calling cloudobjects ...");
            passthru("cloudobjects configuration-job:create ".$fullName.".xml");
        }
    }

}
