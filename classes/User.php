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

	function validateEmail() {
		return Validate::email($this->email, true);
	}

	function validateNickname() {
		return Validate::string($this->nickname, array('min_length' => 1, 'max_length' => 64,
													   'format' => VALIDATE_ALPHA_LOWER . VALIDATE_NUM));
	}
}
