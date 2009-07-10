<?php
/*
 * Laconica - a distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, Control Yourself, Inc.
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

    function getTaggedNotices($tag, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0, $since=null)
    {
        $ids = Notice::stream(array($this, '_streamTaggedDirect'),
                              array($tag),
                              'profile:notice_ids_tagged:' . $this->id . ':' . $tag,
                              $offset, $limit, $since_id, $max_id, $since);
        return Notice::getStreamByIds($ids);
    }

    function getNotices($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $max_id=0, $since=null)
    {
        // XXX: I'm not sure this is going to be any faster. It probably isn't.
        $ids = Notice::stream(array($this, '_streamDirect'),
                              array(),
                              'profile:notice_ids:' . $this->id,
                              $offset, $limit, $since_id, $max_id, $since);

        return Notice::getStreamByIds($ids);
    }

    function _streamTaggedDirect($tag, $offset, $limit, $since_id, $max_id, $since)
    {
        // XXX It would be nice to do this without a join

        $notice = new Notice();

        $query =
          "select id from notice join notice_tag on id=notice_id where tag='".
          $notice->escape($tag) .
          "' and profile_id=" . $notice->escape($this->id);

        if ($since_id != 0) {
            $query .= " and id > $since_id";
        }

        if ($max_id != 0) {
            $query .= " and id < $max_id";
        }

        if (!is_null($since)) {
            $query .= " and created > '" . date('Y-m-d H:i:s', $since) . "'";
        }

        $query .= ' order by id DESC';

        if (!is_null($offset)) {
            $query .= " limit $offset, $limit";
        }

        $notice->query($query);

        $ids = array();

        while ($notice->fetch()) {
            $ids[] = $notice->id;
        }

        return $ids;
    }

    function _streamDirect($offset, $limit, $since_id, $max_id, $since = null)
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

    function getSubscriptions($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN subscription ' .
          'ON profile.id = subscription.subscribed ' .
          'WHERE subscription.subscriber = %d ' .
          'AND subscription.subscribed != subscription.subscriber ' .
          'ORDER BY subscription.created DESC ';

        if (common_config('db','type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $profile = new Profile();

        $profile->query(sprintf($qry, $this->id));

        return $profile;
    }

    function getSubscribers($offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN subscription ' .
          'ON profile.id = subscription.subscriber ' .
          'WHERE subscription.subscribed = %d ' .
          'AND subscription.subscribed != subscription.subscriber ' .
          'ORDER BY subscription.created DESC ';

        if ($offset) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $profile = new Profile();

        $cnt = $profile->query(sprintf($qry, $this->id));

        return $profile;
    }

    function subscriptionCount()
    {
        $c = common_memcache();

        if (!empty($c)) {
            $cnt = $c->get(common_cache_key('profile:subscription_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $sub = new Subscription();
        $sub->subscriber = $this->id;

        $cnt = (int) $sub->count('distinct subscribed');

        $cnt = ($cnt > 0) ? $cnt - 1 : $cnt;

        if (!empty($c)) {
            $c->set(common_cache_key('profile:subscription_count:'.$this->id), $cnt);
        }

        common_debug("subscriptionCount == $cnt");
        return $cnt;
    }

    function subscriberCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $cnt = $c->get(common_cache_key('profile:subscriber_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $sub = new Subscription();
        $sub->subscribed = $this->id;

        $cnt = (int) $sub->count('distinct subscriber');

        $cnt = ($cnt > 0) ? $cnt - 1 : $cnt;

        if (!empty($c)) {
            $c->set(common_cache_key('profile:subscriber_count:'.$this->id), $cnt);
        }

        common_debug("subscriberCount == $cnt");
        return $cnt;
    }

    function faveCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $cnt = $c->get(common_cache_key('profile:fave_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $faves = new Fave();
        $faves->user_id = $this->id;
        $cnt = (int) $faves->count('distinct notice_id');

        if (!empty($c)) {
            $c->set(common_cache_key('profile:fave_count:'.$this->id), $cnt);
        }

        common_debug("faveCount == $cnt");
        return $cnt;
    }

    function noticeCount()
    {
        $c = common_memcache();

        if (!empty($c)) {
            $cnt = $c->get(common_cache_key('profile:notice_count:'.$this->id));
            if (is_integer($cnt)) {
                return (int) $cnt;
            }
        }

        $notices = new Notice();
        $notices->profile_id = $this->id;
        $cnt = (int) $notices->count('distinct id');

        if (!empty($c)) {
            $c->set(common_cache_key('profile:notice_count:'.$this->id), $cnt);
        }

        common_debug("noticeCount == $cnt");
        return $cnt;
    }

    function blowSubscriberCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $c->delete(common_cache_key('profile:subscriber_count:'.$this->id));
        }
    }

    function blowSubscriptionCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $c->delete(common_cache_key('profile:subscription_count:'.$this->id));
        }
    }

    function blowFaveCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $c->delete(common_cache_key('profile:fave_count:'.$this->id));
        }
    }

    function blowNoticeCount()
    {
        $c = common_memcache();
        if (!empty($c)) {
            $c->delete(common_cache_key('profile:notice_count:'.$this->id));
        }
    }
}
