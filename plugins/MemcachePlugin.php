<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
 *
 * Plugin to implement cache interface for memcache
 *
 * PHP version 5
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

if (!defined('STATUSNET')) {
    // This check helps protect against security problems;
    // your code file can't be executed directly from the web.
    exit(1);
}

/**
 * A plugin to use memcache for the cache interface
 *
 * This used to be encoded as config-variable options in the core code;
 * it's now broken out to a separate plugin. The same interface can be
 * implemented by other plugins.
 *
 * @category  Cache
 * @package   StatusNet
 * @author    Evan Prodromou <evan@status.net>
 * @copyright 2009 StatusNet, Inc.
 * @license   http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License version 3.0
 * @link      http://status.net/
 */

class MemcachePlugin extends Plugin
{
    private $_conn  = null;
    public $servers = array('127.0.0.1;11211');

    public $compressThreshold = 20480;
    public $compressMinSaving = 0.2;

    public $persistent = null;

    /**
     * Initialize the plugin
     *
     * Note that onStartCacheGet() may have been called before this!
     *
     * @return boolean flag value
     */

    function onInitializePlugin()
    {
        if (is_null($this->persistent)) {
            $this->persistent = (php_sapi_name() == 'cli') ? false : true;
        }
        $this->_ensureConn();
        return true;
    }

    /**
     * Get a value associated with a key
     *
     * The value should have been set previously.
     *
     * @param string &$key   in; Lookup key
     * @param mixed  &$value out; value associated with key
     *
     * @return boolean hook success
     */

    function onStartCacheGet(&$key, &$value)
    {
        $this->_ensureConn();
        $value = $this->_conn->get($key);
        Event::handle('EndCacheGet', array($key, &$value));
        return false;
    }

    /**
     * Associate a value with a key
     *
     * @param string  &$key     in; Key to use for lookups
     * @param mixed   &$value   in; Value to associate
     * @param integer &$flag    in; Flag (passed through to Memcache)
     * @param integer &$expiry  in; Expiry (passed through to Memcache)
     * @param boolean &$success out; Whether the set was successful
     *
     * @return boolean hook success
     */

    function onStartCacheSet(&$key, &$value, &$flag, &$expiry, &$success)
    {
        $this->_ensureConn();
        $success = $this->_conn->set($key, $value, $flag, $expiry);
        Event::handle('EndCacheSet', array($key, $value, $flag,
                                           $expiry));
        return false;
    }

    /**
     * Delete a value associated with a key
     *
     * @param string  &$key     in; Key to lookup
     * @param boolean &$success out; whether it worked
     *
     * @return boolean hook success
     */

    function onStartCacheDelete(&$key, &$success)
    {
        $this->_ensureConn();
        $success = $this->_conn->delete($key);
        Event::handle('EndCacheDelete', array($key));
        return false;
    }

    /**
     * Ensure that a connection exists
     *
     * Checks the instance $_conn variable and connects
     * if it is empty.
     *
     * @return void
     */

    private function _ensureConn()
    {
        if (empty($this->_conn)) {
            $this->_conn = new Memcache();

            if (is_array($this->servers)) {
                foreach ($this->servers as $server) {
                    list($host, $port) = explode(';', $server);
                    if (empty($port)) {
                        $port = 11211;
                    }

                    $this->_conn->addServer($host, $port, $this->persistent);
                }
            } else {
                $this->_conn->addServer($this->servers, $this->persistent);
                list($host, $port) = explode(';', $this->servers);
                if (empty($port)) {
                    $port = 11211;
                }
                $this->_conn->addServer($host, $port, $this->persistent);
            }

            // Compress items stored in the cache if they're over threshold in size
            // (default 2KiB) and the compression would save more than min savings
            // ratio (default 0.2).

            // Allows the cache to store objects larger than 1MB (if they
            // compress to less than 1MB), and improves cache memory efficiency.

            $this->_conn->setCompressThreshold($this->compressThreshold,
                                               $this->compressMinSaving);
        }
    }

    function onPluginVersion(&$versions)
    {
        $versions[] = array('name' => 'Memcache',
                            'version' => STATUSNET_VERSION,
                            'author' => 'Evan Prodromou, Craig Andrews',
                            'homepage' => 'http://status.net/wiki/Plugin:Memcache',
                            'rawdescription' =>
                            _m('Use <a href="http://memcached.org/">Memcached</a> to cache query results.'));
        return true;
    }
}

