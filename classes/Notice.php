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
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Notice extends Memcached_DataObject 
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
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Notice',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

	function getProfile() {
		return Profile::staticGet($this->profile_id);
	}

	function saveTags() {
		/* extract all #hastags */
		$count = preg_match_all('/(?:^|\s)#([A-Za-z0-9_\-\.]{1,64})/', strtolower($this->content), $match);
		if (!$count) {
			return true;
		}
		
		/* elide characters we don't want in the tag */
		$match[1] = str_replace(array('-', '_', '.'), '', $match[1]);

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

	static function saveNew($profile_id, $content, $source=NULL, $is_local=1, $reply_to=NULL, $uri=NULL) {
		
		$notice = new Notice();
		$notice->profile_id = $profile_id;
		$notice->is_local = $is_local;
		$notice->reply_to = $reply_to;
		$notice->created = common_sql_now();
		$notice->content = $content;
		$notice->rendered = common_render_content($notice->content, $notice);
		$notice->source = $source;
		$notice->uri = $uri;
		
		$id = $notice->insert();

		if (!$id) {
			common_log_db_error($notice, 'INSERT', __FILE__);
			return _('Problem saving notice.');
		}

		# Update the URI after the notice is in the database
		if (!$uri) {
			$orig = clone($notice);
			$notice->uri = common_notice_uri($notice);

			if (!$notice->update($orig)) {
				common_log_db_error($notice, 'UPDATE', __FILE__);
				return _('Problem saving notice.');
			}
		}

		# XXX: do we need to change this for remote users?
		
		common_save_replies($notice);
		$notice->saveTags();

		# Clear the cache for subscribed users, so they'll update at next request
		# XXX: someone clever could prepend instead of clearing the cache
		
		if (common_config('memcached', 'enabled')) {
			$notice->blowSubsCache();
		}
		
		return $notice;
	}
	
	function blowSubsCache() {
		$cache = common_memcache();
		if ($cache) {
			$user = new User();
			
			$user->query('SELECT id ' .
						 'FROM user JOIN subscription ON user.id = subscription.subscriber ' .
						 'WHERE subscription.subscribed = ' . $this->profile_id);
			
			while ($user->fetch()) {
				$cache->delete(common_cache_key('user:notices_with_friends:' . $user->id));
			}
			
			$user->free();
			unset($user);
		}
	}
}
