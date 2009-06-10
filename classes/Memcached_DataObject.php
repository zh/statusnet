<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, Controlez-Vous, Inc.
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
 */

if (!defined('LACONICA')) { exit(1); }

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Memcached_DataObject extends DB_DataObject
{
    function &staticGet($cls, $k, $v=null)
    {
        if (is_null($v)) {
            $v = $k;
            # XXX: HACK!
            $i = new $cls;
            $keys = $i->keys();
            $k = $keys[0];
            unset($i);
        }
        $i = Memcached_DataObject::getcached($cls, $k, $v);
        if ($i) {
            return $i;
        } else {
            $i = DB_DataObject::staticGet($cls, $k, $v);
            if ($i) {
                $i->encache();
            }
            return $i;
        }
    }

    function &pkeyGet($cls, $kv)
    {
        $i = Memcached_DataObject::multicache($cls, $kv);
        if ($i) {
            return $i;
        } else {
            $i = new $cls();
            foreach ($kv as $k => $v) {
                $i->$k = $v;
            }
            if ($i->find(true)) {
                $i->encache();
            } else {
                $i = null;
            }
            return $i;
        }
    }

    function insert()
    {
        $result = parent::insert();
        return $result;
    }

    function update($orig=null)
    {
        if (is_object($orig) && $orig instanceof Memcached_DataObject) {
            $orig->decache(); # might be different keys
        }
        $result = parent::update($orig);
        if ($result) {
            $this->encache();
        }
        return $result;
    }

    function delete()
    {
        $this->decache(); # while we still have the values!
        return parent::delete();
    }

    static function memcache() {
        return common_memcache();
    }

    static function cacheKey($cls, $k, $v) {
        return common_cache_key(strtolower($cls).':'.$k.':'.$v);
    }

    static function getcached($cls, $k, $v) {
        $c = Memcached_DataObject::memcache();
        if (!$c) {
            return false;
        } else {
            return $c->get(Memcached_DataObject::cacheKey($cls, $k, $v));
        }
    }

    function keyTypes()
    {
        global $_DB_DATAOBJECT;
        if (!isset($_DB_DATAOBJECT['INI'][$this->_database][$this->__table."__keys"])) {
            $this->databaseStructure();

        }
        return $_DB_DATAOBJECT['INI'][$this->_database][$this->__table."__keys"];
    }

    function encache()
    {
        $c = $this->memcache();
        if (!$c) {
            return false;
        } else {
            $pkey = array();
            $pval = array();
            $types = $this->keyTypes();
            ksort($types);
            foreach ($types as $key => $type) {
                if ($type == 'K') {
                    $pkey[] = $key;
                    $pval[] = $this->$key;
                } else {
                    $c->set($this->cacheKey($this->tableName(), $key, $this->$key), $this);
                }
            }
            # XXX: should work for both compound and scalar pkeys
            $pvals = implode(',', $pval);
            $pkeys = implode(',', $pkey);
            $c->set($this->cacheKey($this->tableName(), $pkeys, $pvals), $this);
        }
    }

    function decache()
    {
        $c = $this->memcache();
        if (!$c) {
            return false;
        } else {
            $pkey = array();
            $pval = array();
            $types = $this->keyTypes();
            ksort($types);
            foreach ($types as $key => $type) {
                if ($type == 'K') {
                    $pkey[] = $key;
                    $pval[] = $this->$key;
                } else {
                    $c->delete($this->cacheKey($this->tableName(), $key, $this->$key));
                }
            }
            # should work for both compound and scalar pkeys
            # XXX: comma works for now but may not be safe separator for future keys
            $pvals = implode(',', $pval);
            $pkeys = implode(',', $pkey);
            $c->delete($this->cacheKey($this->tableName(), $pkeys, $pvals));
        }
    }

    function multicache($cls, $kv)
    {
        ksort($kv);
        $c = Memcached_DataObject::memcache();
        if (!$c) {
            return false;
        } else {
            $pkeys = implode(',', array_keys($kv));
            $pvals = implode(',', array_values($kv));
            return $c->get(Memcached_DataObject::cacheKey($cls, $pkeys, $pvals));
        }
    }

    function getSearchEngine($table)
    {
        require_once INSTALLDIR.'/lib/search_engines.php';
        static $search_engine;
        if (!isset($search_engine)) {
                $connected = false;
                if (common_config('sphinx', 'enabled')) {
                    $search_engine = new SphinxSearch($this, $table);
                    $connected = $search_engine->is_connected();
                }

                // unable to connect to sphinx' search daemon
                if (!$connected) {
                    if ('mysql' === common_config('db', 'type')) {
                        $search_engine = new MySQLSearch($this, $table);
                    } else {
                        $search_engine = new PGSearch($this, $table);
                    }
                }
        }
        return $search_engine;
    }

    static function cachedQuery($cls, $qry, $expiry=3600)
    {
        $c = Memcached_DataObject::memcache();
        if (!$c) {
            $inst = new $cls();
            $inst->query($qry);
            return $inst;
        }
        $key_part = common_keyize($cls).':'.md5($qry);
        $ckey = common_cache_key($key_part);
        $stored = $c->get($ckey);
        if ($stored) {
            return new ArrayWrapper($stored);
        }

        $inst = new $cls();
        $inst->query($qry);
        $cached = array();
        while ($inst->fetch()) {
            $cached[] = clone($inst);
        }
        $inst->free();
        $c->set($ckey, $cached, MEMCACHE_COMPRESSED, $expiry);
        return new ArrayWrapper($cached);
    }

    // We overload so that 'SET NAMES "utf8"' is called for
    // each connection

    function _connect()
    {
        global $_DB_DATAOBJECT;
        $exists = !empty($this->_database_dsn_md5) &&
          isset($_DB_DATAOBJECT['CONNECTIONS'][$this->_database_dsn_md5]);
        $result = parent::_connect();
        if (!$exists) {
            $DB = &$_DB_DATAOBJECT['CONNECTIONS'][$this->_database_dsn_md5];
            if (common_config('db', 'type') == 'mysql' &&
                common_config('db', 'utf8')) {
                $conn = $DB->connection;
                if ($DB instanceof DB_mysqli) {
                    mysqli_set_charset($conn, 'utf8');
                } else if ($DB instanceof DB_mysql) {
                    mysql_set_charset('utf8', $conn);
                }
            }
        }
        return $result;
    }
}
