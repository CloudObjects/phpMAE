<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Injectables;

use Psr\SimpleCache\CacheInterface, Psr\SimpleCache\CacheException;
use Psr\Http\Message\MessageInterface;
use Doctrine\Common\Cache\Cache;
use GuzzleHttp\Psr7;

class CacheBridge implements CacheInterface {

    private $cache;
    private $prefix;

    public function __construct(Cache $cache, string $prefix) {
        $this->cache = $cache;
        $this->prefix = $prefix;
    }

    public function get($key, $default = null) {
        $value = $this->cache->fetch($this->prefix.$key);
        if (is_array($value) && isset($value['container']) && isset($value['body'])
                && is_object($value['container']) && is_a($value['container'], MessageInterface::class)
                && is_string($value['body'])) {
            // Convert message interface back
            $stream = Psr7\stream_for($value['body']);
            $value = $value['container']
                ->withBody($stream);
        }
        return ($value === false ? $default : $value);
    }

    public function set($key, $value, $ttl = null) {
        if (is_object($value) && is_a($value, MessageInterface::class)) {
            // Read full stream for caching
            $stream = $value->getBody();
            $value = [
                'container' => $value,
                'body' => $stream->getContents()
            ];
            $stream->rewind();
        }
        return $this->cache->save($this->prefix.$key, $value, $ttl);
    }

    public function delete($key) {
        throw new CacheException("This method is not implemented.");
    }

    public function clear() {
        throw new CacheException("This method is not implemented.");
    }

    public function getMultiple($keys, $default = null) {
        throw new CacheException("This method is not implemented.");
    }

    public function setMultiple($values, $ttl = null) {
        throw new CacheException("This method is not implemented.");
    }

    public function deleteMultiple($keys) {
        throw new CacheException("This method is not implemented.");
    }

    public function has($key) {
        return $this->cache->contains($this->prefix.$key);
    }

}