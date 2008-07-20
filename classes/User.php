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
 * Table Definition for user
 */
require_once 'DB/DataObject.php';
require_once 'Validate.php';

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
    public $emailpost;                       // tinyint(1)   default_1
    public $jabber;                          // varchar(255)  unique_key
    public $jabbernotify;                    // tinyint(1)  
    public $jabberreplies;                   // tinyint(1)  
    public $updatefrompresence;              // tinyint(1)  
    public $sms;                             // varchar(64)  unique_key
    public $carrier;                         // int(4)  
    public $smsnotify;                       // tinyint(1)  
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
		foreach (array('nickname', 'email', 'jabber', 'sms', 'carrier') as $k) {
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
								  'search', 'avatar');
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
}
