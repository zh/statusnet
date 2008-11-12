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

/* We keep the first three 20-notice pages, plus one for pagination check,
 * in the memcached cache. */

define('NOTICE_CACHE_WINDOW', 61);

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
		return Profile::staticGet('id', $this->profile_id);
	}

	function delete() {
		$this->blowCaches();
		$this->blowFavesCache();
		$this->blowInboxes();
		parent::delete();
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
			$notice->blowCaches();
		}

		$notice->addToInboxes();
		return $notice;
	}

	function blowCaches() {
		$this->blowSubsCache();
		$this->blowNoticeCache();
		$this->blowRepliesCache();
		$this->blowPublicCache();
		$this->blowTagCache();
	}

	function blowTagCache() {
		$cache = common_memcache();
		if ($cache) {
			$tag = new Notice_tag();
			$tag->notice_id = $this->id;
			if ($tag->find()) {
				while ($tag->fetch()) {
					$cache->delete(common_cache_key('notice_tag:notice_stream:' . $tag->tag));
				}
			}
			$tag->free();
			unset($tag);
		}
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

	function blowNoticeCache() {
		if ($this->is_local) {
			$cache = common_memcache();
			if ($cache) {
				$cache->delete(common_cache_key('user:notices:'.$this->profile_id));
			}
		}
	}

	function blowRepliesCache() {
		$cache = common_memcache();
		if ($cache) {
			$reply = new Reply();
			$reply->notice_id = $this->id;
			if ($reply->find()) {
				while ($reply->fetch()) {
					$cache->delete(common_cache_key('user:replies:'.$reply->profile_id));
				}
			}
			$reply->free();
			unset($reply);
		}
	}

	function blowPublicCache() {
		if ($this->is_local) {
			$cache = common_memcache();
			if ($cache) {
				$cache->delete(common_cache_key('public'));
			}
		}
	}

	function blowFavesCache() {
		$cache = common_memcache();
		if ($cache) {
			$fave = new Fave();
			$fave->notice_id = $this->id;
			if ($fave->find()) {
				while ($fave->fetch()) {
					$cache->delete(common_cache_key('user:faves:'.$fave->user_id));
				}
			}
			$fave->free();
			unset($fave);
		}
	}

	# XXX: too many args; we need to move to named params or even a separate
	# class for notice streams
	
	static function getStream($qry, $cachekey, $offset=0, $limit=20, $since_id=0, $before_id=0, $order=NULL) {

		if (common_config('memcached', 'enabled')) {

			# Skip the cache if this is a since_id or before_id qry
			if ($since_id > 0 || $before_id > 0) {
				return Notice::getStreamDirect($qry, $offset, $limit, $since_id, $before_id, $order);
			} else {
				return Notice::getCachedStream($qry, $cachekey, $offset, $limit, $order);
			}
		}

		return Notice::getStreamDirect($qry, $offset, $limit, $since_id, $before_id, $order);
	}

	static function getStreamDirect($qry, $offset, $limit, $since_id, $before_id, $order) {

		$needAnd = FALSE;
	  	$needWhere = TRUE;

		if (preg_match('/\bWHERE\b/i', $qry)) {
			$needWhere = FALSE;
			$needAnd = TRUE;
		}

		if ($since_id > 0) {

			if ($needWhere) {
		    	$qry .= ' WHERE ';
				$needWhere = FALSE;
			} else {
				$qry .= ' AND ';
			}

		    $qry .= ' notice.id > ' . $since_id;
		}

		if ($before_id > 0) {

			if ($needWhere) {
		    	$qry .= ' WHERE ';
				$needWhere = FALSE;
			} else {
				$qry .= ' AND ';
			}

			$qry .= ' notice.id < ' . $before_id;
		}

		# Allow ORDER override
		
		if ($order) {
			$qry .= $order;
		} else {
			$qry .= ' ORDER BY notice.created DESC, notice.id DESC ';
		}

		if (common_config('db','type') == 'pgsql') {
			$qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
		} else {
			$qry .= ' LIMIT ' . $offset . ', ' . $limit;
		}

		$notice = new Notice();

		$notice->query($qry);

		return $notice;
	}

	static function getCachedStream($qry, $cachekey, $offset, $limit, $order) {

		# If outside our cache window, just go to the DB

		if ($offset + $limit > NOTICE_CACHE_WINDOW) {
			return Notice::getStreamDirect($qry, $offset, $limit, NULL, NULL, $order);
		}

		# Get the cache; if we can't, just go to the DB

		$cache = common_memcache();

		if (!$cache) {
			return Notice::getStreamDirect($qry, $offset, $limit, NULL, NULL, $order);
		}

		# Get the notices out of the cache

		$notices = $cache->get(common_cache_key($cachekey));

		# On a cache hit, return a DB-object-like wrapper

		if ($notices !== FALSE) {
			$wrapper = new NoticeWrapper(array_slice($notices, $offset, $limit));
			return $wrapper;
		}

		# Otherwise, get the full cache window out of the DB

		$notice = Notice::getStreamDirect($qry, 0, NOTICE_CACHE_WINDOW, NULL, NULL, $order);

		# If there are no hits, just return the value

		if (!$notice) {
			return $notice;
		}

		# Pack results into an array

		$notices = array();

		while ($notice->fetch()) {
			$notices[] = clone($notice);
		}

		# Store the array in the cache for next time

		$result = $cache->set(common_cache_key($cachekey), $notices);

		# return a wrapper of the array for use now

		$wrapper = new NoticeWrapper(array_slice($notices, $offset, $limit));

		return $wrapper;
	}

	function publicStream($offset=0, $limit=20, $since_id=0, $before_id=0) {

		$needAnd = FALSE;
      	$needWhere = TRUE;

		$qry = 'SELECT * FROM notice ';

		if (common_config('public', 'localonly')) {
			$qry .= ' WHERE is_local = 1 ';
			$needWhere = FALSE;
			$needAnd = TRUE;
		}

		return Notice::getStream($qry,
								 'public',
								 $offset, $limit, $since_id, $before_id);
	}

	function addToInboxes() {

		$inbox = new Notice_inbox();

		$inbox->query('INSERT INTO notice_inbox (user_id, notice_id) ' .
					  'SELECT user.id, ' . $this->id . ' ' .
					  'FROM user JOIN subscription ON user.id = subscription.subscriber ' .
					  'WHERE subscription.subscribed = ' . $this->profile_id);

		return;
	}

	# Delete from inboxes if we're deleted.

	function blowInboxes() {

		$inbox = new Notice_inbox();
		$inbox->notice_id = $this->id;
		$inbox->delete();

		return;
	}

}

