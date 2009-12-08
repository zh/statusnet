<?php
/*
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Table Definition for location_namespace
 */

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Forward extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'forward';                         // table name
    public $profile_id;                      // int(4)  primary_key not_null
    public $notice_id;                       // int(4)  primary_key not_null
    public $created;                         // datetime   not_null default_0000-00-00%2000%3A00%3A00

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Forward',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function &pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Forward', $kv);
    }

    static function saveNew($profile_id, $notice_id)
    {
        $forward = new Forward();

        $forward->profile_id = $profile_id;
        $forward->notice_id  = $notice_id;
        $forward->created    = common_sql_now();

        $forward->query('BEGIN');

        if (!$forward->insert()) {
            throw new ServerException(_("Couldn't insert forward."));
        }

        $ni = $forward->addToInboxes();

        $forward->query('COMMIT');

        $forward->blowCache($ni);

        return $forward;
    }

    function addToInboxes()
    {
        $inbox = new Notice_inbox();

        $user = new User();

        $user->query('SELECT id FROM user JOIN subscription ON user.id = subscription.subscriber '.
                     'WHERE subscription.subscribed = '.$this->profile_id);

        $ni = array();

        while ($user->fetch()) {
            $inbox = Notice_inbox::pkeyGet(array('user_id' => $user->id,
                                                 'notice_id' => $this->notice_id));

            if (empty($inbox)) {
                $ni[$user->id] = NOTICE_INBOX_SOURCE_FORWARD;
            } else {
                $inbox->free();
            }
        }

        $user->free();

        Notice_inbox::bulkInsert($this->notice_id, $this->created, $ni);

        return $ni;
    }

    function blowCache($ni)
    {
        $cache = common_memcache();

        if (!empty($cache)) {
            foreach ($ni as $id => $source) {
                $cache->delete(common_cache_key('notice_inbox:by_user:'.$id));
                $cache->delete(common_cache_key('notice_inbox:by_user_own:'.$id));
                $cache->delete(common_cache_key('notice_inbox:by_user:'.$id.';last'));
                $cache->delete(common_cache_key('notice_inbox:by_user_own:'.$id.';last'));
            }
        }
    }
}
