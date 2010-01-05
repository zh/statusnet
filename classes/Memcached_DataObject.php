<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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

if (!defined('STATUSNET') && !defined('LACONICA')) { exit(1); }

class Memcached_DataObject extends DB_DataObject
{
    /**
     * Destructor to free global memory resources associated with
     * this data object when it's unset or goes out of scope.
     * DB_DataObject doesn't do this yet by itself.
     */

    function __destruct()
    {
        $this->free();
        if (method_exists('DB_DataObject', '__destruct')) {
            parent::__destruct();
        }
    }

    /**
     * Magic function called at serialize() time.
     *
     * We use this to drop a couple process-specific references
     * from DB_DataObject which can cause trouble in future
     * processes.
     *
     * @return array of variable names to include in serialization.
     */
    function __sleep()
    {
        $vars = array_keys(get_object_vars($this));
        $skip = array('_DB_resultid', '_link_loaded');
        return array_diff($vars, $skip);
    }

    /**
     * Magic function called at unserialize() time.
     *
     * Clean out some process-specific variables which might
     * be floating around from a previous process's cached
     * objects.
     *
     * Old cached objects may still have them.
     */
    function __wakeup()
    {
        // Refers to global state info from a previous process.
        // Clear this out so we don't accidentally break global
        // state in *this* process.
        $this->_DB_resultid = null;
        // We don't have any local DBO refs, so clear these out.
        $this->_link_loaded = false;
    }

    /**
     * Wrapper for DB_DataObject's static lookup using memcached
     * as backing instead of an in-process cache array.
     *
     * @param string $cls classname of object type to load
     * @param mixed $k key field name, or value for primary key
     * @param mixed $v key field value, or leave out for primary key lookup
     * @return mixed Memcached_DataObject subtype or false
     */
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
        if ($i !== false) { // false == cache miss
            return $i;
        } else {
            $i = DB_DataObject::factory($cls);
            if (empty($i)) {
                return false;
            }
            $result = $i->get($k, $v);
            if ($result) {
                $i->encache();
                return $i;
            } else {
                // save the fact that no such row exists
                $c = self::memcache();
                if (!empty($c)) {
                    $ck = self::cachekey($cls, $k, $v);
                    $c->set($ck, null);
                }
                return false;
            }
        }
    }

    function &pkeyGet($cls, $kv)
    {
        $i = Memcached_DataObject::multicache($cls, $kv);
        if ($i !== false) { // false == cache miss
            return $i;
        } else {
            $i = DB_DataObject::factory($cls);
            if (empty($i)) {
                return false;
            }
            foreach ($kv as $k => $v) {
                $i->$k = $v;
            }
            if ($i->find(true)) {
                $i->encache();
            } else {
                $i = null;
                $c = self::memcache();
                if (!empty($c)) {
                    $ck = self::multicacheKey($cls, $kv);
                    $c->set($ck, null);
                }
            }
            return $i;
        }
    }

    function insert()
    {
        $this->decache(); // in case of cached negative lookups
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
        if (is_object($cls) || is_object($k) || is_object($v)) {
            $e = new Exception();
            common_log(LOG_ERR, __METHOD__ . ' object in param: ' .
                str_replace("\n", " ", $e->getTraceAsString()));
        }
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
        }

        $keys = $this->_allCacheKeys();

        foreach ($keys as $key) {
            $c->set($key, $this);
        }
    }

    function decache()
    {
        $c = $this->memcache();

        if (!$c) {
            return false;
        }

        $keys = $this->_allCacheKeys();

        foreach ($keys as $key) {
            $c->delete($key, $this);
        }
    }

    function _allCacheKeys()
    {
        $ckeys = array();

        $types = $this->keyTypes();
        ksort($types);

        $pkey = array();
        $pval = array();

        foreach ($types as $key => $type) {

            assert(!empty($key));

            if ($type == 'U') {
                if (empty($this->$key)) {
                    continue;
                }
                $ckeys[] = $this->cacheKey($this->tableName(), $key, $this->$key);
            } else if ($type == 'K' || $type == 'N') {
                $pkey[] = $key;
                $pval[] = $this->$key;
            } else {
                throw new Exception("Unknown key type $key => $type for " . $this->tableName());
            }
        }

        assert(count($pkey) > 0);

        // XXX: should work for both compound and scalar pkeys
        $pvals = implode(',', $pval);
        $pkeys = implode(',', $pkey);

        $ckeys[] = $this->cacheKey($this->tableName(), $pkeys, $pvals);

        return $ckeys;
    }

    function multicache($cls, $kv)
    {
        ksort($kv);
        $c = self::memcache();
        if (!$c) {
            return false;
        } else {
            return $c->get(self::multicacheKey($cls, $kv));
        }
    }

    static function multicacheKey($cls, $kv)
    {
        ksort($kv);
        $pkeys = implode(',', array_keys($kv));
        $pvals = implode(',', array_values($kv));
        return self::cacheKey($cls, $pkeys, $pvals);
    }

    function getSearchEngine($table)
    {
        require_once INSTALLDIR.'/lib/search_engines.php';
        static $search_engine;
        if (!isset($search_engine)) {
            if (Event::handle('GetSearchEngine', array($this, $table, &$search_engine))) {
                if ('mysql' === common_config('db', 'type')) {
                    $type = common_config('search', 'type');
                    if ($type == 'like') {
                        $search_engine = new MySQLLikeSearch($this, $table);
                    } else if ($type == 'fulltext') {
                        $search_engine = new MySQLSearch($this, $table);
                    } else {
                        throw new ServerException('Unknown search type: ' . $type);
                    }
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

        if ($stored !== false) {
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

        $sum = $this->_getDbDsnMD5();

        if (!empty($_DB_DATAOBJECT['CONNECTIONS'][$sum]) &&
            !PEAR::isError($_DB_DATAOBJECT['CONNECTIONS'][$sum])) {
            $exists = true;
        } else {
            $exists = false;
       }

        $result = parent::_connect();

        if ($result && !$exists) {
            $DB = &$_DB_DATAOBJECT['CONNECTIONS'][$this->_database_dsn_md5];
            if (common_config('db', 'type') == 'mysql' &&
                common_config('db', 'utf8')) {
                $conn = $DB->connection;
                if (!empty($conn)) {
                    if ($DB instanceof DB_mysqli) {
                        mysqli_set_charset($conn, 'utf8');
                    } else if ($DB instanceof DB_mysql) {
                        mysql_set_charset('utf8', $conn);
                    }
                }
            }
        }

        return $result;
    }

    // XXX: largely cadged from DB_DataObject

    function _getDbDsnMD5()
    {
        if ($this->_database_dsn_md5) {
            return $this->_database_dsn_md5;
        }

        $dsn = $this->_getDbDsn();

        if (is_string($dsn)) {
            $sum = md5($dsn);
        } else {
            /// support array based dsn's
            $sum = md5(serialize($dsn));
        }

        return $sum;
    }

    function _getDbDsn()
    {
        global $_DB_DATAOBJECT;

        if (empty($_DB_DATAOBJECT['CONFIG'])) {
            DB_DataObject::_loadConfig();
        }

        $options = &$_DB_DATAOBJECT['CONFIG'];

        // if the databse dsn dis defined in the object..

        $dsn = isset($this->_database_dsn) ? $this->_database_dsn : null;

        if (!$dsn) {

            if (!$this->_database) {
                $this->_database = isset($options["table_{$this->__table}"]) ? $options["table_{$this->__table}"] : null;
            }

            if ($this->_database && !empty($options["database_{$this->_database}"]))  {
                $dsn = $options["database_{$this->_database}"];
            } else if (!empty($options['database'])) {
                $dsn = $options['database'];
            }
        }

        if (!$dsn) {
            throw new Exception("No database name / dsn found anywhere");
        }

        return $dsn;
    }
}
