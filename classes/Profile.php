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
 * Table Definition for profile
 */
require_once 'DB/DataObject.php';

class Profile extends DB_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile';                         // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)   not_null
    public $fullname;                        // varchar(255)
    public $profileurl;                      // varchar(255)
    public $homepage;                        // varchar(255)
    public $bio;                             // varchar(140)
    public $location;                        // varchar(255)
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return DB_DataObject::staticGet('Profile',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

	function getAvatar($width, $height=NULL) {
		$avatar = DB_DataObject::factory('avatar');
		$avatar->profile_id = $this->id;
		$avatar->width = $width;
		if (is_null($height)) {
			$avatar->height = $width;
		} else {
			$avatar->height = $height;
		}
		if ($avatar->find(true)) {
			return $avatar;
		} else {
			return NULL;
		}
	}

	function getOriginalAvatar() {
		$avatar = DB_DataObject::factory('avatar');
		$avatar->profile_id = $this->id;
		$avatar->original = true;
		if ($avatar->find(true)) {
			return $avatar;
		} else {
			return NULL;
		}
	}

	function validateNickname() {
		return Validate::string($this->nickname, array('min_length' => 1, 'max_length' => 64,
													   'format' => VALIDATE_ALPHA_LOWER . VALIDATE_NUM));
	}

	function validateProfileurl() {
		return Validate::uri($this->profileurl, array('allowed_schemes' => array('http', 'https')));
	}

	function validateHomepage() {
		return (strlen($this->homepage) == 0) ||
		  Validate::uri($this->homepage, array('allowed_schemes' => array('http', 'https'))));
	}

	function validateBio() {
		return Validate::string($this->bio, array('min_length' => 0, 'max_length' => 140));
	}

	function validateLocation() {
		return Validate::string($this->location, array('min_length' => 0, 'max_length' => 255));
	}

	function validateFullname() {
		return Validate::string($this->fullname, array('min_length' => 0, 'max_length' => 255));
	}
}
