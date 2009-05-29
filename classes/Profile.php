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
    function staticGet($k,$v=null)
    { return Memcached_DataObject::staticGet('Profile',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function getAvatar($width, $height=null)
    {
        if (is_null($height)) {
            $height = $width;
        }
        return Avatar::pkeyGet(array('profile_id' => $this->id,
                                     'width' => $width,
                                     'height' => $height));
    }

    function getOriginalAvatar()
    {
        $avatar = DB_DataObject::factory('avatar');
        $avatar->profile_id = $this->id;
        $avatar->original = true;
        if ($avatar->find(true)) {
            return $avatar;
        } else {
            return null;
        }
    }

    function setOriginal($filename)
    {
        $imagefile = new ImageFile($this->id, Avatar::path($filename));

        $avatar = new Avatar();
        $avatar->profile_id = $this->id;
        $avatar->width = $imagefile->width;
        $avatar->height = $imagefile->height;
        $avatar->mediatype = image_type_to_mime_type($imagefile->type);
        $avatar->filename = $filename;
        $avatar->original = true;
        $avatar->url = Avatar::url($filename);
        $avatar->created = DB_DataObject_Cast::dateTime(); # current time

        # XXX: start a transaction here

        if (!$this->delete_avatars() || !$avatar->insert()) {
            @unlink(Avatar::path($filename));
            return null;
        }

        foreach (array(AVATAR_PROFILE_SIZE, AVATAR_STREAM_SIZE, AVATAR_MINI_SIZE) as $size) {
            # We don't do a scaled one if original is our scaled size
            if (!($avatar->width == $size && $avatar->height == $size)) {

                $scaled_filename = $imagefile->resize($size);

                //$scaled = DB_DataObject::factory('avatar');
                $scaled = new Avatar();
                $scaled->profile_id = $this->id;
                $scaled->width = $size;
                $scaled->height = $size;
                $scaled->original = false;
                $scaled->mediatype = image_type_to_mime_type($imagefile->type);
                $scaled->filename = $scaled_filename;
                $scaled->url = Avatar::url($scaled_filename);
                $scaled->created = DB_DataObject_Cast::dateTime(); # current time

                if (!$scaled->insert()) {
                    return null;
                }
            }
        }

        return $avatar;
    }

    function delete_avatars($original=true)
    {
        $avatar = new Avatar();
        $avatar->profile_id = $this->id;
        $avatar->find();
        while ($avatar->fetch()) {
            if ($avatar->original) {
                if ($original == false) {
                    continue;
                }
            }
            $avatar->delete();
        }
        return true;
    }

    function getBestName()
    {
        return ($this->fullname) ? $this->fullname : $this->nickname;
    }

    # Get latest notice on or before date; default now
    function getCurrentNotice($dt=null)
    {
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
        return null;
    }

    function getNotices($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0)
    {
        // XXX: I'm not sure this is going to be any faster. It probably isn't.
        $ids = Notice::stream(array($this, '_streamDirect'),
                              array(),
                              'profile:notice_ids:' . $this->id,
                              $offset, $limit, $since_id, $max_id);

        return Notice::getStreamByIds($ids);
    }

    function _streamDirect($offset, $limit, $since_id, $max_id, $since)
    {
        $notice = new Notice();

        $notice->profile_id = $this->id;

        $notice->selectAdd();
        $notice->selectAdd('id');

        if ($since_id != 0) {
            $notice->whereAdd('id > ' . $since_id);
        }

        if ($max_id != 0) {
            $notice->whereAdd('id <= ' . $max_id);
        }

        if (!is_null($since)) {
            $notice->whereAdd('created > \'' . date('Y-m-d H:i:s', $since) . '\'');
        }

        $notice->orderBy('id DESC');

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        $ids = array();

        if ($notice->find()) {
            while ($notice->fetch()) {
                $ids[] = $notice->id;
            }
        }

        return $ids;
    }

    function isMember($group)
    {
        $mem = new Group_member();

        $mem->group_id = $group->id;
        $mem->profile_id = $this->id;

        if ($mem->find()) {
            return true;
        } else {
            return false;
        }
    }

    function isAdmin($group)
    {
        $mem = new Group_member();

        $mem->group_id = $group->id;
        $mem->profile_id = $this->id;
        $mem->is_admin = 1;

        if ($mem->find()) {
            return true;
        } else {
            return false;
        }
    }

    function avatarUrl($size=AVATAR_PROFILE_SIZE)
    {
        $avatar = $this->getAvatar($size);
        if ($avatar) {
            return $avatar->displayUrl();
        } else {
            return Avatar::defaultImage($size);
        }
    }
}
