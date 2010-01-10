<?php
/**
 * Table Definition for status_network
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2009, StatusNet, Inc.
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

class Status_network extends DB_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'status_network';                  // table name
    public $nickname;                        // varchar(64)  primary_key not_null
    public $hostname;                        // varchar(255)  unique_key
    public $pathname;                        // varchar(255)  unique_key
    public $dbhost;                          // varchar(255)
    public $dbuser;                          // varchar(255)
    public $dbpass;                          // varchar(255)
    public $dbname;                          // varchar(255)
    public $sitename;                        // varchar(255)
    public $theme;                           // varchar(255)
    public $logo;                            // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Status_network',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static $cache = null;
    static $base = null;

    static function setupDB($dbhost, $dbuser, $dbpass, $dbname, $servers)
    {
        global $config;

        $config['db']['database_'.$dbname] = "mysqli://$dbuser:$dbpass@$dbhost/$dbname";
        $config['db']['ini_'.$dbname] = INSTALLDIR.'/classes/status_network.ini';
        $config['db']['table_status_network'] = $dbname;

        if (class_exists('Memcache')) {
            self::$cache = new Memcache();

            if (is_array($servers)) {
                foreach($servers as $server) {
                    self::$cache->addServer($server);
                }
            } else {
                self::$cache->addServer($servers);
            }
        }

        self::$base = $dbname;
    }

    static function cacheKey($k, $v) {
        return 'statusnet:' . self::$base . ':status_network:'.$k.':'.$v;
    }

    static function memGet($k, $v)
    {
        if (!self::$cache) {
            return self::staticGet($k, $v);
        }

        $ck = self::cacheKey($k, $v);

        $sn = self::$cache->get($ck);

        if (empty($sn)) {
            $sn = self::staticGet($k, $v);
            if (!empty($sn)) {
                self::$cache->set($ck, $sn);
            }
        }

        return $sn;
    }

    function decache()
    {
        if (self::$cache) {
            $keys = array('nickname', 'hostname', 'pathname');
            foreach ($keys as $k) {
                $ck = self::cacheKey($k, $this->$k);
                self::$cache->delete($ck);
            }
        }
    }

    function update($orig=null)
    {
        if (is_object($orig)) {
            $orig->decache(); # might be different keys
        }
        return parent::update($orig);
    }

    function delete()
    {
        $this->decache(); # while we still have the values!
        return parent::delete();
    }

    static function setupSite($servername, $pathname, $wildcard)
    {
        global $config;

        $sn = null;

        // XXX I18N, probably not crucial for hostnames
        // XXX This probably needs a tune up

        if (0 == strncasecmp(strrev($wildcard), strrev($servername), strlen($wildcard))) {
            // special case for exact match
            if (0 == strcasecmp($servername, $wildcard)) {
                $sn = self::memGet('nickname', '');
            } else {
                $parts = explode('.', $servername);
                $sn = self::memGet('nickname', strtolower($parts[0]));
            }
        } else {
            $sn = self::memGet('hostname', strtolower($servername));

            if (empty($sn)) {
                // Try for a no-www address
                if (0 == strncasecmp($servername, 'www.', 4)) {
                    $sn = self::memGet('hostname', strtolower(substr($servername, 4)));
                }
            }
        }

        if (!empty($sn)) {

            // Redirect to the right URL

            if (!empty($sn->hostname) &&
                empty($_SERVER['HTTPS']) &&
                0 != strcasecmp($sn->hostname, $servername)) {
                $sn->redirectTo('http://'.$sn->hostname.$_SERVER['REQUEST_URI']);
            } else if (!empty($_SERVER['HTTPS']) &&
                       0 != strcasecmp($sn->sitename.'.'.$wildcard, $servername)) {
                $sn->redirectTo('https://'.$sn->sitename.'.'.$wildcard.$_SERVER['REQUEST_URI']);
            }

            $dbhost = (empty($sn->dbhost)) ? 'localhost' : $sn->dbhost;
            $dbuser = (empty($sn->dbuser)) ? $sn->nickname : $sn->dbuser;
            $dbpass = $sn->dbpass;
            $dbname = (empty($sn->dbname)) ? $sn->nickname : $sn->dbname;

            $config['db']['database'] = "mysqli://$dbuser:$dbpass@$dbhost/$dbname";

            $config['site']['name'] = $sn->sitename;

            if (!empty($sn->theme)) {
                $config['site']['theme'] = $sn->theme;
            }
            if (!empty($sn->logo)) {
                $config['site']['logo'] = $sn->logo;
            }

            return $sn;
        } else {
            return null;
        }
    }

    // Code partially mooked from http://www.richler.de/en/php-redirect/
    // (C) 2006 by Heiko Richler  http://www.richler.de/
    // LGPL

    function redirectTo($destination)
    {
        $old = 'http'.
          (($_SERVER['HTTPS'] == 'on') ? 'S' : '').
          '://'.
          $_SERVER['HTTP_HOST'].
          $_SERVER['REQUEST_URI'].
          $_SERVER['QUERY_STRING'];
        if ($old == $destination) { // this would be a loop!
            // error_log(...) ?
            return false;
        }

        header('HTTP/1.1 301 Moved Permanently');
        header("Location: $destination");

        print "<a href='$destination'>$destination</a>\n";

        exit;
    }
}
