<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

// We keep 5 pages of inbox notices in memcache, +1 for pagination check

define('INBOX_CACHE_WINDOW', 101);

class Notice_inbox extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice_inbox';                    // table name
    public $user_id;                         // int(4)  primary_key not_null
    public $notice_id;                       // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null
    public $source;                          // tinyint(1)   default_1

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Notice_inbox',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function stream($user_id, $offset=0, $limit=20, $since_id=0, $before_id=0, $since=null)
    {
        $cache = common_memcache();

        if (empty($cache) ||
            $since_id != 0 || $before_id != 0 || !is_null($since) ||
            ($offset + $limit) > INBOX_CACHE_WINDOW) {
            common_debug('Doing direct DB hit for notice_inbox since the params are screwy.');
            return Notice_inbox::_streamDirect($user_id, $offset, $limit, $since_id, $before_id, $since);
        }

        $idkey = common_cache_key('notice_inbox:by_user:'.$user_id);

        $idstr = $cache->get($idkey);

        if (!empty($idstr)) {
            // Cache hit! Woohoo!
            common_debug('Cache hit for notice_inbox.');
            $window = explode(',', $idstr);
            $ids = array_slice($window, $offset, $limit);
            return $ids;
        }

        $laststr = common_cache_key($idkey.';last');

        if (!empty($laststr)) {
            common_debug('Cache hit for notice_inbox on last item.');

            $window = explode(',', $laststr);
            $last_id = $window[0];
            $new_ids = Notice_inbox::_streamDirect($user_id, 0, INBOX_CACHE_WINDOW,
                                                   $last_id, null, null);

            $new_window = array_merge($new_ids, $window);

            $new_windowstr = implode(',', $new_window);

            $result = $cache->set($idkey, $new_windowstr);
            $result = $cache->set($idkey . ';last', $new_windowstr);

            $ids = array_slice($new_window, $offset, $limit);

            return $ids;
        }

        $window = Notice_inbox::_streamDirect($user_id, 0, INBOX_CACHE_WINDOW,
                                              null, null, null);

        $windowstr = implode(',', $new_window);

        $result = $cache->set($idkey, $windowstr);
        $result = $cache->set($idkey . ';last', $windowstr);

        $ids = array_slice($window, $offset, $limit);

        return $ids;
    }

    function _streamDirect($user_id, $offset, $limit, $since_id, $before_id, $since)
    {
        $inbox = new Notice_inbox();

        $inbox->user_id = $user_id;

        if ($since_id != 0) {
            $inbox->whereAdd('notice_id > ' . $since_id);
        }

        if ($before_id != 0) {
            $inbox->whereAdd('notice_id < ' . $before_id);
        }

        if (!is_null($since)) {
            $inbox->whereAdd('created > \'' . date('Y-m-d H:i:s', $since) . '\'');
        }

        $inbox->orderBy('notice_id DESC');

        if (!is_null($offset)) {
            $inbox->limit($offset, $limit);
        }

        $ids = array();

        if ($inbox->find()) {
            while ($inbox->fetch()) {
                $ids[] = $inbox->notice_id;
            }
        }

        return $ids;
    }
}
