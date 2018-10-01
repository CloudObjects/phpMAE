<?php

use Symfony\Component\Finder\Finder;

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

    public function sanitizeDependencies() {
        $finder = Finder::create()->files()
            ->name('*.php')
            ->in('vendor');
        
        $parser = (new \PhpParser\ParserFactory)->create(\PhpParser\ParserFactory::PREFER_PHP7);
        $traverser = new \PhpParser\NodeTraverser;
        $printer = new \PhpParser\PrettyPrinter\Standard;        
        $traverser->addVisitor(new \CloudObjects\PhpMAE\Validation\FunctionWhitelistOnlyVisitor);

        foreach ($finder as $file) {
            try {
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

}