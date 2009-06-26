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

define('NOTICE_INBOX_SOURCE_SUB', 1);
define('NOTICE_INBOX_SOURCE_GROUP', 2);
define('NOTICE_INBOX_SOURCE_REPLY', 3);
define('NOTICE_INBOX_SOURCE_GATEWAY', -1);

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

    function stream($user_id, $offset, $limit, $since_id, $max_id, $since, $own=false)
    {
        return Notice::stream(array('Notice_inbox', '_streamDirect'),
                              array($user_id, $own),
                              ($own) ? 'notice_inbox:by_user:'.$user_id :
                              'notice_inbox:by_user_own:'.$user_id,
                              $offset, $limit, $since_id, $max_id, $since);
    }

    function _streamDirect($user_id, $own, $offset, $limit, $since_id, $max_id, $since)
    {
        $inbox = new Notice_inbox();

        $inbox->user_id = $user_id;

        if (!$own) {
            $inbox->whereAdd('source != ' . NOTICE_INBOX_SOURCE_GATEWAY);
        }

        if ($since_id != 0) {
            $inbox->whereAdd('notice_id > ' . $since_id);
        }

        if ($max_id != 0) {
            $inbox->whereAdd('notice_id <= ' . $max_id);
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

    function &pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Notice_inbox', $kv);
    }
}
