<?php
/**
 * StatusNet, the distributed open-source microblogging tool
 *
 * Cache interface plus default in-memory cache implementation
 *
 * PHP version 5
 *
 * LICENCE: This program is free software: you can redistribute it and/or modify
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

/**
 * Interface for caching
 *
 * An abstract interface for caching.
 *
 */

class Cache
{
    var $_items = array();
    static $_inst = null;

    static function instance()
    {
        if (is_null(self::$_inst)) {
            self::$_inst = new Cache();
        }

        return self::$_inst;
    }

    static function key($extra)
    {
        $base_key = common_config('memcached', 'base');

        if (empty($base_key)) {
            $base_key = common_keyize(common_config('site', 'name'));
        }

        return 'statusnet:' . $base_key . ':' . $extra;
    }

    static function keyize($str)
    {
        $str = strtolower($str);
        $str = preg_replace('/\s/', '_', $str);
        return $str;
    }

    function get($key)
    {
        $value = null;

        if (!Event::handle('StartCacheGet', array(&$key, &$value))) {
            if (array_key_exists($key, $this->_items)) {
                common_log(LOG_INFO, 'Cache HIT for key ' . $key);
                $value = $this->_items[$key];
            } else {
                common_log(LOG_INFO, 'Cache MISS for key ' . $key);
            }
            Event::handle('EndCacheGet', array($key, &$value));
        }

        return $value;
    }

    function set($key, $value, $flag=null, $expiry=null)
    {
        $success = false;

        if (!Event::handle('StartCacheSet', array(&$key, &$value, &$flag, &$expiry, &$success))) {
            common_log(LOG_INFO, 'Setting cache value for key ' . $key);
            $this->_items[$key] = $value;
            $success = true;
            Event::handle('EndCacheSet', array($key, $value, $flag, $expiry));
        }

        return $success;
    }

    function delete($key)
    {
        $success = false;

        if (!Event::handle('StartCacheDelete', array(&$key, &$success))) {
            if (array_key_exists($key, $this->_items[$key])) {
                common_log(LOG_INFO, 'Deleting cache value for key ' . $key);
                unset($this->_items[$key]);
            }
            $success = true;
            Event::handle('EndCacheDelete', array($key));
        }

        return $success;
    }
}
