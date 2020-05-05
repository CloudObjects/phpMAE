<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
 
namespace CloudObjects\PhpMAE\Injectables;

use Psr\SimpleCache\CacheInterface, Psr\SimpleCache\CacheException;
use Doctrine\Common\Cache\Cache;

class CacheBridge implements CacheInterface {

    private $cache;
    private $prefix;

    public function __construct(Cache $cache, string $prefix) {
        $this->cache = $cache;
        $this->prefix = $prefix;
    }

    public function get($key, $default = null) {
        $value = $this->cache->fetch($this->prefix.$key);
        return ($value === false ? $default : $value);
    }

    public function set($key, $value, $ttl = null) {
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