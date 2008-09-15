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

/* We keep the first three 20-notice pages, plus one for pagination check,
 * in the memcached cache. */

define('WITHFRIENDS_CACHE_WINDOW', 61);

/**
 * Table Definition for user
 */
require_once 'DB/DataObject.php';
require_once 'Validate.php';
require_once($INSTALLDIR.'/lib/noticewrapper.php');

class User extends DB_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'user';                            // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  unique_key
    public $password;                        // varchar(255)  
    public $email;                           // varchar(255)  unique_key
    public $incomingemail;                   // varchar(255)  unique_key
    public $emailnotifysub;                  // tinyint(1)   default_1
    public $emailmicroid;                    // tinyint(1)   default_1
    public $language;                        // varchar(50)  
    public $timezone;                        // varchar(50)  
    public $emailpost;                       // tinyint(1)   default_1
    public $jabber;                          // varchar(255)  unique_key
    public $jabbernotify;                    // tinyint(1)  
    public $jabberreplies;                   // tinyint(1)  
    public $jabbermicroid;                   // tinyint(1)   default_1
    public $updatefrompresence;              // tinyint(1)  
    public $sms;                             // varchar(64)  unique_key
    public $carrier;                         // int(4)  
    public $smsnotify;                       // tinyint(1)  
    public $smsreplies;                      // tinyint(1)  
    public $smsemail;                        // varchar(255)  
    public $uri;                             // varchar(255)  unique_key
    public $autosubscribe;                   // tinyint(1)  
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('User',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

	function getProfile() {
		$profile = DB_DataObject::factory('profile');
		$profile->id = $this->id;
		if ($profile->find()) {
			$profile->fetch();
			return $profile;
		}
		return NULL;
	}

	function isSubscribed($other) {
		assert(!is_null($other));
		$sub = DB_DataObject::factory('subscription');
		$sub->subscriber = $this->id;
		$sub->subscribed = $other->id;
		return $sub->find();
	}

	# 'update' won't write key columns, so we have to do it ourselves.

	function updateKeys(&$orig) {
		$parts = array();
		foreach (array('nickname', 'email', 'jabber', 'incomingemail', 'sms', 'carrier', 'smsemail', 'language', 'timezone') as $k) {
			if (strcmp($this->$k, $orig->$k) != 0) {
				$parts[] = $k . ' = ' . $this->_quote($this->$k);
			}
		}
		if (count($parts) == 0) {
			# No changes
			return true;
		}
		$toupdate = implode(', ', $parts);
		$qry = 'UPDATE ' . $this->tableName() . ' SET ' . $toupdate .
		  ' WHERE id = ' . $this->id;
		return $this->query($qry);
	}

	function allowed_nickname($nickname) {
		# XXX: should already be validated for size, content, etc.
		static $blacklist = array('rss', 'xrds', 'doc', 'main',
								  'settings', 'notice', 'user',
								  'search', 'avatar', 'tag', 'tags');
		$merged = array_merge($blacklist, common_config('nickname', 'blacklist'));
		return !in_array($nickname, $merged);
	}

	function getCurrentNotice($dt=NULL) {
		$profile = $this->getProfile();
		if (!$profile) {
			return NULL;
		}
		return $profile->getCurrentNotice($dt);
	}
	
	function getCarrier() {
		return Sms_carrier::staticGet($this->carrier);
	}
	
	function subscribeTo($other) {
		$sub = new Subscription();
		$sub->subscriber = $this->id;
		$sub->subscribed = $other->id;

		$sub->created = DB_DataObject_Cast::dateTime(); # current time

		if (!$sub->insert()) {
			return false;
		}
		
		return true;
	}

	function noticesWithFriends($offset=0, $limit=20) {

		# We clearly need a more elegant way to make this work.
		
		if (common_config('memcached', 'enabled')) {
			if ($offset + $limit < WITHFRIENDS_CACHE_WINDOW) {
				$cached = $this->noticesWithFriendsCachedWindow();
				if (!$cached) {
					$cached = $this->noticesWithFriendsWindow();
				}
				$wrapper = new NoticeWrapper(array_slice($cached, $offset, $limit));
				return $wrapper;
			} 
		}
		
		$notice = new Notice();
		
		$notice->query('SELECT notice.* ' .
					   'FROM notice JOIN subscription on notice.profile_id = subscription.subscribed ' .
					   'WHERE subscription.subscriber = ' . $this->id . ' ' .
					   'ORDER BY created DESC, notice.id DESC ' .
					   'LIMIT ' . $offset . ', ' . $limit);
		
		return $notice;
	}
	
	function noticesWithFriendsCachedWindow() {
		$cache = new Memcache();
		$res = $cache->connect(common_config('memcached', 'server'), common_config('memcached', 'port'));
		if (!$res) {
			return NULL;
		}
		$notices = $cache->get(common_cache_key('user:notices_with_friends:' . $this->id));
		return $notices;
	}

	function noticesWithFriendsWindow() {
		
		$cache = new Memcache();
		$res = $cache->connect(common_config('memcached', 'server'), common_config('memcached', 'port'));
		
		if (!$res) {
			return NULL;
		}
		
		$notice = new Notice();
		
		$notice->query('SELECT notice.* ' .
					   'FROM notice JOIN subscription on notice.profile_id = subscription.subscribed ' .
					   'WHERE subscription.subscriber = ' . $this->id . ' ' .
					   'ORDER BY created DESC, notice.id DESC ' .
					   'LIMIT 0, ' . WITHFRIENDS_CACHE_WINDOW);
		
		$notices = array();
		
		while ($notice->fetch()) {
			$notices[] = clone($notice);
		}

		$cache->set(common_cache_key('user:notices_with_friends:' . $this->id), $notices);
		return $notices;
	}
	
	static function register($fields) {

		# MAGICALLY put fields into current scope
		
		extract($fields);
		
		$profile = new Profile();

		$profile->query('BEGIN');

		$profile->nickname = $nickname;
		$profile->profileurl = common_profile_url($nickname);
		
		if ($fullname) {
			$profile->fullname = $fullname;
		}
		if ($homepage) {
			$profile->homepage = $homepage;
		}
		if ($bio) {
			$profile->bio = $bio;
		}
		if ($location) {
			$profile->location = $location;
		}
		
		$profile->created = common_sql_now();
		
		$id = $profile->insert();

		if (!$id) {
			common_log_db_error($profile, 'INSERT', __FILE__);
		    return FALSE;
		}
		
		$user = new User();
		
		$user->id = $id;
		$user->nickname = $nickname;

		if ($password) { # may not have a password for OpenID users
			$user->password = common_munge_password($password, $id);
		}
		
		$user->created = common_sql_now();
		$user->uri = common_user_uri($user);

		$result = $user->insert();

		if (!$result) {
			common_log_db_error($user, 'INSERT', __FILE__);
			return FALSE;
		}

		# Everyone is subscribed to themself

		$subscription = new Subscription();
		$subscription->subscriber = $user->id;
		$subscription->subscribed = $user->id;
		$subscription->created = $user->created;
		
		$result = $subscription->insert();
		
		if (!$result) {
			common_log_db_error($subscription, 'INSERT', __FILE__);
			return FALSE;
		}
		
		if ($email) {

			$confirm = new Confirm_address();
			$confirm->code = common_confirmation_code(128);
			$confirm->user_id = $user->id;
			$confirm->address = $email;
			$confirm->address_type = 'email';

			$result = $confirm->insert();
			if (!$result) {
				common_log_db_error($confirm, 'INSERT', __FILE__);
				return FALSE;
			}
		}

		$profile->query('COMMIT');

		if ($email) {
			mail_confirm_address($confirm->code,
								 $profile->nickname,
								 $email);
		}

		return $user;
	}
}
