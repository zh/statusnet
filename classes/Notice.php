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

/**
 * Table Definition for notice
 */
require_once 'DB/DataObject.php';

class Notice extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice';                          // table name
    public $id;                              // int(4)  primary_key not_null
    public $profile_id;                      // int(4)   not_null
    public $uri;                             // varchar(255)  unique_key
    public $content;                         // varchar(140)  
    public $rendered;                        // text()  
    public $url;                             // varchar(255)  
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP
    public $reply_to;                        // int(4)  
    public $is_local;                        // tinyint(1)  
    public $source;                          // varchar(32)  

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Notice',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

	function getProfile() {
		return Profile::staticGet($this->profile_id);
	}

	function saveTags() {
		/* extract all #hastags */
		$count = preg_match_all('/(?:^|\s)#([a-z0-9]{1,64})/', strtolower($this->content), $match);
		if (!$count) {
			return true;
		}

		/* Add them to the database */
		foreach(array_unique($match[1]) as $hashtag) {
			$tag = DB_DataObject::factory('Notice_tag');
			$tag->notice_id = $this->id;
			$tag->tag = $hashtag;
			$tag->created = $this->created;
			$id = $tag->insert();
			if (!$id) {
				$last_error = PEAR::getStaticProperty('DB_DataObject','lastError');
				common_log(LOG_ERR, 'DB error inserting hashtag: ' . $last_error->message);
				common_server_error(sprintf(_('DB error inserting hashtag: %s'), $last_error->message));
				return;
			}
		}
		return true;
	}
	
	static function saveNew($profile_id, $content, $source=NULL, $is_local=1, $reply_to=NULL) {
		
		$notice = new Notice();
		$notice->profile_id = $profile_id;
		$notice->is_local = $is_local;
		$notice->reply_to = $reply_to;
		$notice->created = DB_DataObject_Cast::dateTime();
		$notice->content = $content;
		$notice->rendered = common_render_content($notice->content, $notice);
		if ($source) {
			$notice->source = $source;
		}
		
		$id = $notice->insert();

		if (!$id) {
			return _('Problem saving notice.');
		}

		$orig = clone($notice);
		$notice->uri = common_notice_uri($notice);

		if (!$notice->update($orig)) {
			return _('Problem saving notice.');
		}

		common_save_replies($notice);
		$notice->saveTags();
		
		return $notice;
	}
}
