<?php
/**
 * Data class for favorites talley
 *
 * PHP version 5
 *
 * @category Data
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 *
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2010, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

require_once INSTALLDIR . '/classes/Memcached_DataObject.php';

/**
 * Data class for favorites tally
 *
 * A class representing a total number of times a notice has been favorited
 *
 * @category Action
 * @package  StatusNet
 * @author   Zach Copley <zach@status.net>
 * @license  http://www.fsf.org/licensing/licenses/agpl.html AGPLv3
 * @link     http://status.net/
 */

class Fave_tally extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'fave_tally';          // table name
    public $notice_id;                       // int(4)  primary_key not_null
    public $count;                           // int(4)  primary_key not_null
    public $modified;                        // datetime   not_null default_0000-00-00%2000%3A00%3A00

    /* Static get */
    function staticGet($k, $v = NULL) { return Memcached_DataObject::staticGet('Fave_tally', $k, $v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    /**
     * return table definition for DB_DataObject
     *
     * @return array array of column definitions
     */

    function table()
    {
        return array(
            'notice_id' => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
            'count'     => DB_DATAOBJECT_INT + DB_DATAOBJECT_NOTNULL,
            'modified'  => DB_DATAOBJECT_STR + DB_DATAOBJECT_DATE + DB_DATAOBJECT_TIME + DB_DATAOBJECT_NOTNULL
        );
    }

    /**
     * return key definitions for DB_DataObject
     *
     * @return array key definitions
     */

    function keys()
    {
        return array('notice_id' => 'K');
    }

    /**
     * return key definitions for DB_DataObject
     *
     * @return array key definitions
     */

    function keyTypes()
    {
        return $this->keys();
    }

    /**
     * Magic formula for non-autoincrementing integer primary keys
     *
     * @return array magic three-false array that stops auto-incrementing.
     */

    function sequenceKey()
    {
        return array(false, false, false);
    }

    /**
     * Get a single object with multiple keys
     *
     * @param array $kv Map of key-value pairs
     *
     * @return User_flag_profile found object or null
     */

    function pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Fave_tally', $kv);
    }

    /**
     * Increment a notice's tally
     *
     * @param integer $notice_id   ID of notice we're tallying
     *
     * @return integer             the total times the notice has been faved
     */

    static function increment($notice_id)
    {
        $tally = Fave_tally::ensureTally($notice_id);
        $count = $tally->count + 1;
        $tally->count = $count;
        $result = $tally->update();
        $tally->free();

        if ($result === false) {
            $msg = sprintf(
                _m("Couldn't update favorite tally for notice ID %d.", $notice_id)
            );
            throw new ServerException($msg);
        }

        return $count;
    }

    /**
     * Decrement a notice's tally
     *
     * @param integer $notice_id   ID of notice we're tallying
     *
     * @return integer             the total times the notice has been faved
     */

    static function decrement($notice_id)
    {
        $tally = Fave_tally::ensureTally($notice_id);

        $count = 0;

        if ($tally->count > 0) {
            $count = $tally->count - 1;
            $tally->count = $count;
            $result = $tally->update();
            $tally->free();

            if ($result === false) {
                $msg = sprintf(
                    _m("Couldn't update favorite tally for notice ID %d.", $notice_id)
                );
                throw new ServerException($msg);
            }
        }

        return $count;
    }

    /**
     * Ensure a tally exists for a given notice. If we can't find
     * one create one.
     *
     * @param integer $notice_id
     *
     * @return Fave_tally the tally data object
     */

    static function ensureTally($notice_id)
    {
        $tally = new Fave_tally();
        $result = $tally->get($notice_id);

        if (empty($result)) {
            $tally->notice_id = $notice_id;
            $tally->count = 0;
            if ($tally->insert() === false) {
                $msg = sprintf(
                    _m("Couldn't create favorite tally for notice ID %d.", $notice_id)
                );
                throw new ServerException($msg);
            }
        }

        return $tally;
    }
}
