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
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

class Profile extends Memcached_DataObject 
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'profile';                         // table name
    public $id;                              // int(4)  primary_key not_null
    public $nickname;                        // varchar(64)  multiple_key not_null
    public $fullname;                        // varchar(255)  multiple_key
    public $profileurl;                      // varchar(255)  
    public $homepage;                        // varchar(255)  multiple_key
    public $bio;                             // varchar(140)  multiple_key
    public $location;                        // varchar(255)  multiple_key
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('Profile',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function getSearchEngine() {
        require_once INSTALLDIR.'/classes/SearchEngines.php';
        static $search_engine;
        if (!isset($search_engine)) {
                if (common_config('sphinx', 'enabled')) {
                    $search_engine = new SphinxSearch($this);
                } elseif ('mysql' === common_config('db', 'type')) {
                    $search_engine = new MySQLSearch($this);
                } else {
                    $search_engine = new PGSearch($this);
                }
        }
        return $search_engine;
    }

	function getAvatar($width, $height=NULL) {
		if (is_null($height)) {
			$height = $width;
		}
		return Avatar::pkeyGet(array('profile_id' => $this->id,
									 'width' => $width,
									 'height' => $height));
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

	function setOriginal($source) {

		$info = @getimagesize($source);

		if (!$info) {
			return NULL;
		}

		$filename = common_avatar_filename($this->id,
										   image_type_to_extension($info[2]),
										   NULL, common_timestamp());
		$filepath = common_avatar_path($filename);

		copy($source, $filepath);

		$avatar = new Avatar();

		$avatar->profile_id = $this->id;
		$avatar->width = $info[0];
		$avatar->height = $info[1];
		$avatar->mediatype = image_type_to_mime_type($info[2]);
		$avatar->filename = $filename;
		$avatar->original = true;
		$avatar->url = common_avatar_url($filename);
		$avatar->created = DB_DataObject_Cast::dateTime(); # current time

		# XXX: start a transaction here

		if (!$this->delete_avatars()) {
			@unlink($filepath);
			return NULL;
		}

		if (!$avatar->insert()) {
			@unlink($filepath);
			return NULL;
		}

		foreach (array(AVATAR_PROFILE_SIZE, AVATAR_STREAM_SIZE, AVATAR_MINI_SIZE) as $size) {
			# We don't do a scaled one if original is our scaled size
			if (!($avatar->width == $size && $avatar->height == $size)) {
				$s = $avatar->scale($size);
				if (!$s) {
					return NULL;
				}
			}
		}

		return $avatar;
	}

	function delete_avatars() {
		$avatar = new Avatar();
		$avatar->profile_id = $this->id;
		$avatar->find();
		while ($avatar->fetch()) {
			$avatar->delete();
		}
		return true;
	}

	function getBestName() {
		return ($this->fullname) ? $this->fullname : $this->nickname;
	}

    # Get latest notice on or before date; default now
	function getCurrentNotice($dt=NULL) {
		$notice = new Notice();
		$notice->profile_id = $this->id;
		if ($dt) {
			$notice->whereAdd('created < "' . $dt . '"');
		}
		$notice->orderBy('created DESC, notice.id DESC');
		$notice->limit(1);
		if ($notice->find(true)) {
			return $notice;
		}
		return NULL;
	}
}
