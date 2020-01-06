<?php

use Symfony\Component\Finder\Finder;
use CloudObjects\SDK\COIDParser, CloudObjects\SDK\ObjectRetriever;
use Webmozart\Assert\Assert;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks {
    
    public function phar() {
        // based on example in http://robo.li/tasks/Development/#packphar
        
        $pharTask = $this->taskPackPhar('phpmae.phar')
            ->compress()
            ->addFile('phpmae.php', 'phpmae.php')
            ->addFile('config.php', 'config.php')
            ->addFile('web/index.php', 'web/index.php')
            ->stub('stub.php');

        $finder = Finder::create()
            ->name('*.php')
            ->in('classes');

        foreach ($finder as $file) {
            $pharTask->addFile('classes/'.$file->getRelativePathname(), $file->getRealPath());
        }

        $finder = Finder::create()->files()
            ->name('*.php')
            ->in('vendor');

        foreach ($finder as $file) {
            $pharTask->addStripped('vendor/'.$file->getRelativePathname(), $file->getRealPath());
        }
        $pharTask->run();

        // Verify Phar is packed correctly
        $code = $this->_exec('php phpmae.phar');
    }

    public function sanitizeDependencies($directory) {
        require_once "vendor/autoload.php";

        $finder = Finder::create()->files()
            ->name('*.php')
            ->in($directory)
            ->notPath('composer');

        foreach ($finder as $file) {
            try {
                // Reinitialize sandbox for every file to prevent memory leaks
                $sandbox = new \CloudObjects\PhpMAE\Sandbox\CustomizedSandbox;
                $parser = (new \PhpParser\ParserFactory)->create(\PhpParser\ParserFactory::PREFER_PHP7);
                $traverser = new \PhpParser\NodeTraverser;
                $printer = new \PhpParser\PrettyPrinter\Standard;        
                $traverser->addVisitor(new \CloudObjects\PhpMAE\Sandbox\FunctionExecutorWrapperVisitor($sandbox));

                $ast = $parser->parse(file_get_contents($file));
                $traverser->traverse($ast);
                file_put_contents($file, $printer->prettyPrintFile($ast));
            } catch (\Exception $e) {
                $this->say($file.': '.get_class($e).' '.$e->getMessage());
            }
        }
    }

    public function buildFunctionWhitelist() {
        $allFunctions = get_defined_functions()['internal'];
        $whitelisted = file_exists('data/function.whitelist.json')
            ? json_decode(file_get_contents('data/function.whitelist.json'), true) : [];
        $blacklisted = file_exists('data/function.blacklist.json')
            ? json_decode(file_get_contents('data/function.blacklist.json'), true) : [];
        
        $client = new \GuzzleHttp\Client([ 'base_uri' => 'http://php.net/manual/en/' ]);
        $autoBlacklistPrefix = '';

        $progress = (count($whitelisted) + count($blacklisted)) / count($allFunctions) * 100;
        $this->say($progress.'% of functions handled, let\'s do the rest ...');

        foreach ($allFunctions as $f) {
            // Ignore if already handled
            if (in_array($f, $whitelisted) || in_array($f, $blacklisted))
                continue;
            
            $this->yell($f);

            if ($autoBlacklistPrefix != '' && substr($f, 0, strlen($autoBlacklistPrefix)) == $autoBlacklistPrefix) {
                $this->say('Blacklisted automatically.');
                $blacklisted[] = $f;
                continue;
            }
            $autoBlacklistPrefix = '';

            try {
                // Get and display documentation
                $crawler = new \Symfony\Component\DomCrawler\Crawler(
                    $client->get('function.'.str_replace('_', '-', $f).'.php')->getBody()->getContents()
                );
                $this->say($crawler->filter('.refpurpose')->text());
                $this->say($crawler->filter('.refsect1.description')->text());
            } catch (\Exception $e) {
                $this->say('Error while retrieving information: '.$e->getMessage());
            }
            // Ask what to do
            $action = null;
            while (!in_array($action, ['w','b','a','s','x']))
                $action = $this->ask('(w)hitelist, (b)lacklist, (a)ll blacklist, (s)kip, e(x)it');
            
            switch ($action) {
                case "w":
                    $whitelisted[] = $f;
                    break;
                case "b":
                    $blacklisted[] = $f;
                    break;
                case "a":
                    $blacklisted[] = $f;
                    $autoBlacklistPrefix = substr($f, 0, strpos($f, '_')+1);
                    break;
                case "x":
                    file_put_contents('data/function.whitelist.json', json_encode($whitelisted));
                    file_put_contents('data/function.blacklist.json', json_encode($blacklisted));
                    exit(0);
            }
            // Save on every step to prevent data loss from crashes
            file_put_contents('data/function.whitelist.json', json_encode($whitelisted));
            file_put_contents('data/function.blacklist.json', json_encode($blacklisted));
        }
    }

    public function installStack($stack) {
        require_once "vendor/autoload.php";

        // Fetch stack definition from CloudObjects Core
        $stackCoid = COIDParser::fromString($stack);
        $retriever = new ObjectRetriever([
            'auth_ns' => 'phpmae.cloudobjects.io',
            'auth_secret' => getenv('PHPMAE_SHARED_SECRET')
        ]);
        $stackObject = $retriever->getObject($stackCoid);
        
        $composerFileContent = $retriever->getAttachment($stackCoid,
            $stackObject->getProperty('coid://phpmae.cloudobjects.io/hasAttachedComposerFile')
            ->getId());
        Assert::startsWith($composerFileContent, '{');
        
        $lockFileContent = $retriever->getAttachment($stackCoid,
            $stackObject->getProperty('coid://phpmae.cloudobjects.io/hasAttachedLockFile')
            ->getId());
        Assert::startsWith($lockFileContent, '{');

        $stackDir = __DIR__.'/stacks/'.md5($stack);

        // Install stack
        $this->taskFilesystemStack()
            ->mkdir($stackDir)
            ->run();
        $this->taskWriteToFile($stackDir.'/composer.json')
            ->text($composerFileContent)
            ->run();
        $this->taskWriteToFile($stackDir.'/composer.lock')
            ->text($lockFileContent)
            ->run();

        // Find whitelisted classes
        $whitelistedClasses = [];
        foreach ($stackObject->getProperty('coid://phpmae.cloudobjects.io/whitelistsClassname')
                as $whitelistedClass)
            $whitelistedClasses[] = $whitelistedClass->getValue();
            
        $this->taskWriteToFile($stackDir.'/meta.json')
            ->text(json_encode([
                'rev' => $stackObject->getProperty(ObjectRetriever::REVISION_PROPERTY)->getValue(),
                'whitelisted_classes' => $whitelistedClasses
            ]))
            ->run();
            
        $this->taskComposerInstall()
            ->dir($stackDir)
            ->run();

        // Sanitize stack
        $this->sanitizeDependencies($stackDir);
    }

}