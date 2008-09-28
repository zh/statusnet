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
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Notice_tag',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE
	
	static function noticeStream($tag, $offset=0, $limit=20) {
		$qry = 
		  'SELECT notice.* ' .
		  'FROM notice JOIN notice_tag ON notice.id = notice_tag.notice_id ' .
		  'WHERE notice_tag.tag = "%s"';

		return Notice::getStream(sprintf($qry, $tag),
								 'notice_tag:notice_stream:' . $tag,
								 $offset, $limit);
	}
}
