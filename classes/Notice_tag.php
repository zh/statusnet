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

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Notice_tag extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice_tag';                      // table name
    public $tag;                             // varchar(64)  primary_key not_null
    public $notice_id;                       // int(4)  primary_key not_null
    public $created;                         // datetime()   not_null

    /* Static get */
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Notice_tag',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    static function getStream($tag, $offset=0, $limit=20) {

        $ids = Notice::stream(array('Notice_tag', '_streamDirect'),
                              array($tag),
                              'notice_tag:notice_ids:' . common_keyize($tag),
                              $offset, $limit);

        return Notice::getStreamByIds($ids);
    }

    function _streamDirect($tag, $offset, $limit, $since_id, $max_id, $since)
    {
        $nt = new Notice_tag();

        $nt->tag = $tag;

        $nt->selectAdd();
        $nt->selectAdd('notice_id');

        if ($since_id != 0) {
            $nt->whereAdd('notice_id > ' . $since_id);
        }

        if ($max_id != 0) {
            $nt->whereAdd('notice_id < ' . $max_id);
        }

        if (!is_null($since)) {
            $nt->whereAdd('created > \'' . date('Y-m-d H:i:s', $since) . '\'');
        }

        $nt->orderBy('notice_id DESC');

        if (!is_null($offset)) {
            $nt->limit($offset, $limit);
        }

        $ids = array();

        if ($nt->find()) {
            while ($nt->fetch()) {
                $ids[] = $nt->notice_id;
            }
        }

        return $ids;
    }

    function blowCache($blowLast=false)
    {
        $cache = common_memcache();
        if ($cache) {
            $idkey = common_cache_key('notice_tag:notice_ids:' . common_keyize($this->tag));
            $cache->delete($idkey);
            if ($blowLast) {
                $cache->delete($idkey.';last');
            }
        }
    }

    function &pkeyGet($kv)
    {
        return Memcached_DataObject::pkeyGet('Notice_tag', $kv);
    }
}
