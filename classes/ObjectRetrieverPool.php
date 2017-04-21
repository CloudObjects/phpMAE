<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

namespace CloudObjects\PhpMAE;

use GuzzleHttp\Client;
use CloudObjects\SDK\ObjectRetriever;

class ObjectRetrieverPool {

    private $baseObjectRetriever;
    private $objectRetrievers = [];

    public function __construct(ObjectRetriever $baseObjectRetriever, $baseHostname) {
        $this->baseObjectRetriever = $baseObjectRetriever;
        $this->objectRetrievers[$baseHostname] = $baseObjectRetriever;
    }

    public function getObjectRetriever($hostname) {
        if (!isset($this->baseHostname))
            // Never create a new retriever when using developer credentials
            return $this->baseObjectRetriever;

        if (!isset($this->objectRetrievers[$hostname])) {
			$config = $this->baseObjectRetriever->getClient()->getConfig();
			$config['headers']['C-Act-As'] = $hostname;

			$retriever = new ObjectRetriever;
			$retriever->setClient(new Client($config));
			$this->objectRetrievers[$hostname] = $retriever;
        }

        return $this->objectRetrievers[$hostname];
    }
}

