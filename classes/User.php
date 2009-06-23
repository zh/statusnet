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

if (!defined('LACONICA')) {
    exit(1);
}

/**
 * Table Definition for user
 */

require_once INSTALLDIR.'/classes/Memcached_DataObject.php';
require_once 'Validate.php';

class User extends Memcached_DataObject
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
    public $emailnotifyfav;                  // tinyint(1)   default_1
    public $emailnotifynudge;                // tinyint(1)   default_1
    public $emailnotifymsg;                  // tinyint(1)   default_1
    public $emailnotifyattn;                 // tinyint(1)   default_1
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
    public $urlshorteningservice;            // varchar(50)   default_ur1.ca
    public $inboxed;                         // tinyint(1)
    public $design_id;                       // int(4)
    public $viewdesigns;                     // tinyint(1)   default_1
    public $created;                         // datetime()   not_null
    public $modified;                        // timestamp()   not_null default_CURRENT_TIMESTAMP

    /* Static get */
    function staticGet($k,$v=NULL) { return Memcached_DataObject::staticGet('User',$k,$v); }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function getProfile()
    {
        return Profile::staticGet('id', $this->id);
    }

    function isSubscribed($other)
    {
        assert(!is_null($other));
        // XXX: cache results of this query
        $sub = Subscription::pkeyGet(array('subscriber' => $this->id,
                                           'subscribed' => $other->id));
        return (is_null($sub)) ? false : true;
    }

    // 'update' won't write key columns, so we have to do it ourselves.

    function updateKeys(&$orig)
    {
        $parts = array();
        foreach (array('nickname', 'email', 'jabber', 'incomingemail', 'sms', 'carrier', 'smsemail', 'language', 'timezone') as $k) {
            if (strcmp($this->$k, $orig->$k) != 0) {
                $parts[] = $k . ' = ' . $this->_quote($this->$k);
            }
        }
        if (count($parts) == 0) {
            // No changes
            return true;
        }
        $toupdate = implode(', ', $parts);

        $table = $this->tableName();
        if(common_config('db','quote_identifiers')) {
            $table = '"' . $table . '"';
        }
        $qry = 'UPDATE ' . $table . ' SET ' . $toupdate .
          ' WHERE id = ' . $this->id;
        $orig->decache();
        $result = $this->query($qry);
        if ($result) {
            $this->encache();
        }
        return $result;
    }

    function allowed_nickname($nickname)
    {
        // XXX: should already be validated for size, content, etc.
        static $blacklist = array('rss', 'xrds', 'doc', 'main',
                                  'settings', 'notice', 'user',
                                  'search', 'avatar', 'tag', 'tags',
                                  'api', 'message', 'group', 'groups',
                                  'local');
        $merged = array_merge($blacklist, common_config('nickname', 'blacklist'));
        return !in_array($nickname, $merged);
    }

    function getCurrentNotice($dt=null)
    {
        $profile = $this->getProfile();
        if (!$profile) {
            return null;
        }
        return $profile->getCurrentNotice($dt);
    }

    function getCarrier()
    {
        return Sms_carrier::staticGet('id', $this->carrier);
    }

    function subscribeTo($other)
    {
        $sub = new Subscription();
        $sub->subscriber = $this->id;
        $sub->subscribed = $other->id;

        $sub->created = common_sql_now(); // current time

        if (!$sub->insert()) {
            return false;
        }

        return true;
    }

    function hasBlocked($other)
    {

        $block = Profile_block::get($this->id, $other->id);

        if (is_null($block)) {
            $result = false;
        } else {
            $result = true;
            $block->free();
        }

        return $result;
    }

    static function register($fields) {

        // MAGICALLY put fields into current scope

        extract($fields);

        $profile = new Profile();

        $profile->query('BEGIN');

        $profile->nickname = $nickname;
        $profile->profileurl = common_profile_url($nickname);

        if (!empty($fullname)) {
            $profile->fullname = $fullname;
        }
        if (!empty($homepage)) {
            $profile->homepage = $homepage;
        }
        if (!empty($bio)) {
            $profile->bio = $bio;
        }
        if (!empty($location)) {
            $profile->location = $location;
        }

        $profile->created = common_sql_now();

        $id = $profile->insert();

        if (empty($id)) {
            common_log_db_error($profile, 'INSERT', __FILE__);
            return false;
        }

        $user = new User();

        $user->id = $id;
        $user->nickname = $nickname;

        if (!empty($password)) { // may not have a password for OpenID users
            $user->password = common_munge_password($password, $id);
        }

        // Users who respond to invite email have proven their ownership of that address

        if (!empty($code)) {
            $invite = Invitation::staticGet($code);
            if ($invite && $invite->address && $invite->address_type == 'email' && $invite->address == $email) {
                $user->email = $invite->address;
            }
        }

        $inboxes = common_config('inboxes', 'enabled');

        if ($inboxes === true || $inboxes == 'transitional') {
            $user->inboxed = 1;
        }

        $user->created = common_sql_now();
        $user->uri = common_user_uri($user);

        $result = $user->insert();

        if (!$result) {
            common_log_db_error($user, 'INSERT', __FILE__);
            return false;
        }

        // Everyone is subscribed to themself

        $subscription = new Subscription();
        $subscription->subscriber = $user->id;
        $subscription->subscribed = $user->id;
        $subscription->created = $user->created;

        $result = $subscription->insert();

        if (!$result) {
            common_log_db_error($subscription, 'INSERT', __FILE__);
            return false;
        }

        if (!empty($email) && !$user->email) {

            $confirm = new Confirm_address();
            $confirm->code = common_confirmation_code(128);
            $confirm->user_id = $user->id;
            $confirm->address = $email;
            $confirm->address_type = 'email';

            $result = $confirm->insert();
            if (!$result) {
                common_log_db_error($confirm, 'INSERT', __FILE__);
                return false;
            }
        }

        if (!empty($code) && $user->email) {
            $user->emailChanged();
        }

        // Default system subscription

        $defnick = common_config('newuser', 'default');

        if (!empty($defnick)) {
            $defuser = User::staticGet('nickname', $defnick);
            if (empty($defuser)) {
                common_log(LOG_WARNING, sprintf("Default user %s does not exist.", $defnick),
                           __FILE__);
            } else {
                $defsub = new Subscription();
                $defsub->subscriber = $user->id;
                $defsub->subscribed = $defuser->id;
                $defsub->created = $user->created;

                $result = $defsub->insert();

                if (!$result) {
                    common_log_db_error($defsub, 'INSERT', __FILE__);
                    return false;
                }
            }
        }

        $profile->query('COMMIT');

        if ($email && !$user->email) {
            mail_confirm_address($user, $confirm->code, $profile->nickname, $email);
        }

        // Welcome message

        $welcome = common_config('newuser', 'welcome');

        if (!empty($welcome)) {
            $welcomeuser = User::staticGet('nickname', $welcome);
            if (empty($welcomeuser)) {
                common_log(LOG_WARNING, sprintf("Welcome user %s does not exist.", $defnick),
                           __FILE__);
            } else {
                $notice = Notice::saveNew($welcomeuser->id,
                                          sprintf(_('Welcome to %1$s, @%2$s!'),
                                                  common_config('site', 'name'),
                                                  $user->nickname),
                                          'system');
            }
        }

        return $user;
    }

    // Things we do when the email changes

    function emailChanged()
    {

        $invites = new Invitation();
        $invites->address = $this->email;
        $invites->address_type = 'email';

        if ($invites->find()) {
            while ($invites->fetch()) {
                $other = User::staticGet($invites->user_id);
                subs_subscribe_to($other, $this);
            }
        }
    }

    function hasFave($notice)
    {
        $cache = common_memcache();

        // XXX: Kind of a hack.

        if ($cache) {
            // This is the stream of favorite notices, in rev chron
            // order. This forces it into cache.

            $ids = Fave::stream($this->id, 0, NOTICE_CACHE_WINDOW);

            // If it's in the list, then it's a fave

            if (in_array($notice->id, $ids)) {
                return true;
            }

            // If we're not past the end of the cache window,
            // then the cache has all available faves, so this one
            // is not a fave.

            if (count($ids) < NOTICE_CACHE_WINDOW) {
                return false;
            }

            // Otherwise, cache doesn't have all faves;
            // fall through to the default
        }

        $fave = Fave::pkeyGet(array('user_id' => $this->id,
                                    'notice_id' => $notice->id));
        return ((is_null($fave)) ? false : true);
    }

    function mutuallySubscribed($other)
    {
        return $this->isSubscribed($other) &&
          $other->isSubscribed($this);
    }

    function mutuallySubscribedUsers()
    {
        // 3-way join; probably should get cached
        $UT = common_config('db','type')=='pgsql'?'"user"':'user';
        $qry = "SELECT $UT.* " .
          "FROM subscription sub1 JOIN $UT ON sub1.subscribed = $UT.id " .
          "JOIN subscription sub2 ON $UT.id = sub2.subscriber " .
          'WHERE sub1.subscriber = %d and sub2.subscribed = %d ' .
          "ORDER BY $UT.nickname";
        $user = new User();
        $user->query(sprintf($qry, $this->id, $this->id));

        return $user;
    }

    function getReplies($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $before_id=0, $since=null)
    {
        $ids = Reply::stream($this->id, $offset, $limit, $since_id, $before_id, $since);
        return Notice::getStreamByIds($ids);
    }

    function getTaggedNotices($tag, $offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $before_id=0, $since=null) {
        $profile = $this->getProfile();
        if (!$profile) {
            return null;
        } else {
            return $profile->getTaggedNotices($tag, $offset, $limit, $since_id, $before_id, $since);
        }
    }

    function getNotices($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $before_id=0, $since=null)
    {
        $profile = $this->getProfile();
        if (!$profile) {
            return null;
        } else {
            return $profile->getNotices($offset, $limit, $since_id, $before_id, $since);
        }
    }

    function favoriteNotices($offset=0, $limit=NOTICES_PER_PAGE, $own=false)
    {
        $ids = Fave::stream($this->id, $offset, $limit, $own);
        return Notice::getStreamByIds($ids);
    }

    function noticesWithFriends($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $before_id=0, $since=null)
    {
        $enabled = common_config('inboxes', 'enabled');

        // Complicated code, depending on whether we support inboxes yet
        // XXX: make this go away when inboxes become mandatory

        if ($enabled === false ||
            ($enabled == 'transitional' && $this->inboxed == 0)) {
            $qry =
              'SELECT notice.* ' .
              'FROM notice JOIN subscription ON notice.profile_id = subscription.subscribed ' .
              'WHERE subscription.subscriber = %d ' .
              'AND notice.is_local != ' . NOTICE_GATEWAY;
            return Notice::getStream(sprintf($qry, $this->id),
                                     'user:notices_with_friends:' . $this->id,
                                     $offset, $limit, $since_id, $before_id,
                                     $order, $since);
        } else if ($enabled === true ||
                   ($enabled == 'transitional' && $this->inboxed == 1)) {

            $ids = Notice_inbox::stream($this->id, $offset, $limit, $since_id, $before_id, $since, false);

            return Notice::getStreamByIds($ids);
        }
    }

    function noticeInbox($offset=0, $limit=NOTICES_PER_PAGE, $since_id=0, $before_id=0, $since=null)
    {
        $enabled = common_config('inboxes', 'enabled');

        // Complicated code, depending on whether we support inboxes yet
        // XXX: make this go away when inboxes become mandatory

        if ($enabled === false ||
            ($enabled == 'transitional' && $this->inboxed == 0)) {
            $qry =
              'SELECT notice.* ' .
              'FROM notice JOIN subscription ON notice.profile_id = subscription.subscribed ' .
              'WHERE subscription.subscriber = %d ';
            return Notice::getStream(sprintf($qry, $this->id),
                                     'user:notices_with_friends:' . $this->id,
                                     $offset, $limit, $since_id, $before_id,
                                     $order, $since);
        } else if ($enabled === true ||
                   ($enabled == 'transitional' && $this->inboxed == 1)) {

            $ids = Notice_inbox::stream($this->id, $offset, $limit, $since_id, $before_id, $since, true);

            return Notice::getStreamByIds($ids);
        }
    }

    function blowFavesCache()
    {
        $cache = common_memcache();
        if ($cache) {
            // Faves don't happen chronologically, so we need to blow
            // ;last cache, too
            $cache->delete(common_cache_key('fave:ids_by_user:'.$this->id));
            $cache->delete(common_cache_key('fave:ids_by_user:'.$this->id.';last'));
        }
    }

    function getSelfTags()
    {
        return Profile_tag::getTags($this->id, $this->id);
    }

    function setSelfTags($newtags)
    {
        return Profile_tag::setTags($this->id, $this->id, $newtags);
    }

    function block($other)
    {
        // Add a new block record

        $block = new Profile_block();

        // Begin a transaction

        $block->query('BEGIN');

        $block->blocker = $this->id;
        $block->blocked = $other->id;

        $result = $block->insert();

        if (!$result) {
            common_log_db_error($block, 'INSERT', __FILE__);
            return false;
        }

        // Cancel their subscription, if it exists

        $sub = Subscription::pkeyGet(array('subscriber' => $other->id,
                                           'subscribed' => $this->id));

        if ($sub) {
            $result = $sub->delete();
            if (!$result) {
                common_log_db_error($sub, 'DELETE', __FILE__);
                return false;
            }
        }

        $block->query('COMMIT');

        return true;
    }

    function unblock($other)
    {
        // Get the block record

        $block = Profile_block::get($this->id, $other->id);

        if (!$block) {
            return false;
        }

        $result = $block->delete();

        if (!$result) {
            common_log_db_error($block, 'DELETE', __FILE__);
            return false;
        }

        return true;
    }

    function isMember($group)
    {
        $profile = $this->getProfile();
        return $profile->isMember($group);
    }

    function isAdmin($group)
    {
        $profile = $this->getProfile();
        return $profile->isAdmin($group);
    }

    function getGroups($offset=0, $limit=null)
    {
        $qry =
          'SELECT user_group.* ' .
          'FROM user_group JOIN group_member '.
          'ON user_group.id = group_member.group_id ' .
          'WHERE group_member.profile_id = %d ' .
          'ORDER BY group_member.created DESC ';

        if ($offset) {
            if (common_config('db','type') == 'pgsql') {
                $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            } else {
                $qry .= ' LIMIT ' . $offset . ', ' . $limit;
            }
        }

        $groups = new User_group();

        $cnt = $groups->query(sprintf($qry, $this->id));

        return $groups;
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

    function getTaggedSubscribers($tag, $offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN subscription ' .
          'ON profile.id = subscription.subscriber ' .
          'JOIN profile_tag ON (profile_tag.tagged = subscription.subscriber ' .
          'AND profile_tag.tagger = subscription.subscribed) ' .
          'WHERE subscription.subscribed = %d ' .
          "AND profile_tag.tag = '%s' " .
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

        $cnt = $profile->query(sprintf($qry, $this->id, $tag));

        return $profile;
    }

    function getTaggedSubscriptions($tag, $offset=0, $limit=null)
    {
        $qry =
          'SELECT profile.* ' .
          'FROM profile JOIN subscription ' .
          'ON profile.id = subscription.subscribed ' .
          'JOIN profile_tag on (profile_tag.tagged = subscription.subscribed ' .
          'AND profile_tag.tagger = subscription.subscriber) ' .
          'WHERE subscription.subscriber = %d ' .
          "AND profile_tag.tag = '%s' " .
          'AND subscription.subscribed != subscription.subscriber ' .
          'ORDER BY subscription.created DESC ';

        if (common_config('db','type') == 'pgsql') {
            $qry .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        } else {
            $qry .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $profile = new Profile();

        $profile->query(sprintf($qry, $this->id, $tag));

        return $profile;
    }

    function hasOpenID()
    {
        $oid = new User_openid();

        $oid->user_id = $this->id;

        $cnt = $oid->find();

        return ($cnt > 0);
    }

    function getDesign()
    {
        return Design::staticGet('id', $this->design_id);
    }
}
