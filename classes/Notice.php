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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.     See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.     If not, see <http://www.gnu.org/licenses/>.
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
    function staticGet($k,$v=NULL) {
        return Memcached_DataObject::staticGet('Notice',$k,$v);
    }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    function getProfile()
    {
        return Profile::staticGet('id', $this->profile_id);
    }

    function delete()
    {
        $this->blowCaches(true);
        $this->blowFavesCache(true);
        $this->blowSubsCache(true);

        $this->query('BEGIN');
        $related = array('Reply',
                         'Fave',
                         'Notice_tag',
                         'Group_inbox',
                         'Queue_item');
        if (common_config('inboxes', 'enabled')) {
            $related[] = 'Notice_inbox';
        }
        foreach ($related as $cls) {
            $inst = new $cls();
            $inst->notice_id = $this->id;
            $inst->delete();
        }
        $result = parent::delete();
        $this->query('COMMIT');
    }

    function saveTags()
    {
        /* extract all #hastags */
        $count = preg_match_all('/(?:^|\s)#([A-Za-z0-9_\-\.]{1,64})/', strtolower($this->content), $match);
        if (!$count) {
            return true;
        }

        /* Add them to the database */
        foreach(array_unique($match[1]) as $hashtag) {
            /* elide characters we don't want in the tag */
            $this->saveTag($hashtag);
        }
        return true;
    }

    function saveTag($hashtag)
    {
        $hashtag = common_canonical_tag($hashtag);

        $tag = new Notice_tag();
        $tag->notice_id = $this->id;
        $tag->tag = $hashtag;
        $tag->created = $this->created;
        $id = $tag->insert();

        if (!$id) {
            throw new ServerException(sprintf(_('DB error inserting hashtag: %s'),
                                              $last_error->message));
            return;
        }
    }

    static function saveNew($profile_id, $content, $source=null, $is_local=1, $reply_to=null, $uri=null) {

        $profile = Profile::staticGet($profile_id);

        $final =  common_shorten_links($content);

        if (!$profile) {
            common_log(LOG_ERR, 'Problem saving notice. Unknown user.');
            return _('Problem saving notice. Unknown user.');
        }

        if (common_config('throttle', 'enabled') && !Notice::checkEditThrottle($profile_id)) {
            common_log(LOG_WARNING, 'Excessive posting by profile #' . $profile_id . '; throttled.');
            return _('Too many notices too fast; take a breather and post again in a few minutes.');
        }

        if (common_config('site', 'dupelimit') > 0 && !Notice::checkDupes($profile_id, $final)) {
            common_log(LOG_WARNING, 'Dupe posting by profile #' . $profile_id . '; throttled.');
			return _('Too many duplicate messages too quickly; take a breather and post again in a few minutes.');
        }

		$banned = common_config('profile', 'banned');

        if ( in_array($profile_id, $banned) || in_array($profile->nickname, $banned)) {
            common_log(LOG_WARNING, "Attempted post from banned user: $profile->nickname (user id = $profile_id).");
            return _('You are banned from posting notices on this site.');
        }

        $notice = new Notice();
        $notice->profile_id = $profile_id;

        $blacklist = common_config('public', 'blacklist');
        $autosource = common_config('public', 'autosource');

        # Blacklisted are non-false, but not 1, either

        if (($blacklist && in_array($profile_id, $blacklist)) ||
            ($source && $autosource && in_array($source, $autosource))) {
            $notice->is_local = -1;
        } else {
            $notice->is_local = $is_local;
        }

		$notice->query('BEGIN');

		$notice->reply_to = $reply_to;
		$notice->created = common_sql_now();
		$notice->content = $final;
		$notice->rendered = common_render_content($final, $notice);
		$notice->source = $source;
		$notice->uri = $uri;

        if (Event::handle('StartNoticeSave', array(&$notice))) {

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

            $notice->saveReplies();
            $notice->saveTags();
            $notice->saveGroups();

            $notice->addToInboxes();
            $notice->query('COMMIT');

            Event::handle('EndNoticeSave', array($notice));
        }

        # Clear the cache for subscribed users, so they'll update at next request
        # XXX: someone clever could prepend instead of clearing the cache

        if (common_config('memcached', 'enabled')) {
            $notice->blowCaches();
        }

        return $notice;
    }

    static function checkDupes($profile_id, $content) {
        $profile = Profile::staticGet($profile_id);
        if (!$profile) {
            return false;
        }
        $notice = $profile->getNotices(0, NOTICE_CACHE_WINDOW);
        if ($notice) {
            $last = 0;
            while ($notice->fetch()) {
                if (time() - strtotime($notice->created) >= common_config('site', 'dupelimit')) {
                    return true;
                } else if ($notice->content == $content) {
                    return false;
                }
            }
        }
        # If we get here, oldest item in cache window is not
        # old enough for dupe limit; do direct check against DB
        $notice = new Notice();
        $notice->profile_id = $profile_id;
        $notice->content = $content;
        if (common_config('db','type') == 'pgsql')
            $notice->whereAdd('extract(epoch from now() - created) < ' . common_config('site', 'dupelimit'));
        else
            $notice->whereAdd('now() - created < ' . common_config('site', 'dupelimit'));

        $cnt = $notice->count();
        return ($cnt == 0);
    }

    static function checkEditThrottle($profile_id) {
        $profile = Profile::staticGet($profile_id);
        if (!$profile) {
            return false;
        }
        # Get the Nth notice
        $notice = $profile->getNotices(common_config('throttle', 'count') - 1, 1);
        if ($notice && $notice->fetch()) {
            # If the Nth notice was posted less than timespan seconds ago
            if (time() - strtotime($notice->created) <= common_config('throttle', 'timespan')) {
                # Then we throttle
                return false;
            }
        }
        # Either not N notices in the stream, OR the Nth was not posted within timespan seconds
        return true;
    }

    function blowCaches($blowLast=false)
    {
        $this->blowSubsCache($blowLast);
        $this->blowNoticeCache($blowLast);
        $this->blowRepliesCache($blowLast);
        $this->blowPublicCache($blowLast);
        $this->blowTagCache($blowLast);
        $this->blowGroupCache($blowLast);
    }

    function blowGroupCache($blowLast=false)
    {
        $cache = common_memcache();
        if ($cache) {
            $group_inbox = new Group_inbox();
            $group_inbox->notice_id = $this->id;
            if ($group_inbox->find()) {
                while ($group_inbox->fetch()) {
                    $cache->delete(common_cache_key('group:notices:'.$group_inbox->group_id));
                    if ($blowLast) {
                        $cache->delete(common_cache_key('group:notices:'.$group_inbox->group_id.';last'));
                    }
                    $member = new Group_member();
                    $member->group_id = $group_inbox->group_id;
                    if ($member->find()) {
                        while ($member->fetch()) {
                            $cache->delete(common_cache_key('user:notices_with_friends:' . $member->profile_id));
                            if ($blowLast) {
                                $cache->delete(common_cache_key('user:notices_with_friends:' . $member->profile_id . ';last'));
                            }
                        }
                    }
                }
            }
            $group_inbox->free();
            unset($group_inbox);
        }
    }

    function blowTagCache($blowLast=false)
    {
        $cache = common_memcache();
        if ($cache) {
            $tag = new Notice_tag();
            $tag->notice_id = $this->id;
            if ($tag->find()) {
                while ($tag->fetch()) {
                    $cache->delete(common_cache_key('notice_tag:notice_stream:' . $tag->tag));
                    if ($blowLast) {
                        $cache->delete(common_cache_key('notice_tag:notice_stream:' . $tag->tag . ';last'));
                    }
                }
            }
            $tag->free();
            unset($tag);
        }
    }

    function blowSubsCache($blowLast=false)
    {
        $cache = common_memcache();
        if ($cache) {
            $user = new User();

            $UT = common_config('db','type')=='pgsql'?'"user"':'user';
            $user->query('SELECT id ' .

                         "FROM $UT JOIN subscription ON $UT.id = subscription.subscriber " .
                         'WHERE subscription.subscribed = ' . $this->profile_id);

            while ($user->fetch()) {
                $cache->delete(common_cache_key('user:notices_with_friends:' . $user->id));
                if ($blowLast) {
                    $cache->delete(common_cache_key('user:notices_with_friends:' . $user->id . ';last'));
                }
            }
            $user->free();
            unset($user);
        }
    }

    function blowNoticeCache($blowLast=false)
    {
        if ($this->is_local) {
            $cache = common_memcache();
            if ($cache) {
                $cache->delete(common_cache_key('profile:notices:'.$this->profile_id));
                if ($blowLast) {
                    $cache->delete(common_cache_key('profile:notices:'.$this->profile_id.';last'));
                }
            }
        }
    }

    function blowRepliesCache($blowLast=false)
    {
        $cache = common_memcache();
        if ($cache) {
            $reply = new Reply();
            $reply->notice_id = $this->id;
            if ($reply->find()) {
                while ($reply->fetch()) {
                    $cache->delete(common_cache_key('user:replies:'.$reply->profile_id));
                    if ($blowLast) {
                        $cache->delete(common_cache_key('user:replies:'.$reply->profile_id.';last'));
                    }
                }
            }
            $reply->free();
            unset($reply);
        }
    }

    function blowPublicCache($blowLast=false)
    {
        if ($this->is_local == 1) {
            $cache = common_memcache();
            if ($cache) {
                $cache->delete(common_cache_key('public'));
                if ($blowLast) {
                    $cache->delete(common_cache_key('public').';last');
                }
            }
        }
    }

    function blowFavesCache($blowLast=false)
    {
        $cache = common_memcache();
        if ($cache) {
            $fave = new Fave();
            $fave->notice_id = $this->id;
            if ($fave->find()) {
                while ($fave->fetch()) {
                    $cache->delete(common_cache_key('user:faves:'.$fave->user_id));
                    if ($blowLast) {
                        $cache->delete(common_cache_key('user:faves:'.$fave->user_id.';last'));
                    }
                }
            }
            $fave->free();
            unset($fave);
        }
    }

    # XXX: too many args; we need to move to named params or even a separate
    # class for notice streams

    static function getStream($qry, $cachekey, $offset=0, $limit=20, $since_id=0, $before_id=0, $order=null, $since=null) {

        if (common_config('memcached', 'enabled')) {

            # Skip the cache if this is a since, since_id or before_id qry
            if ($since_id > 0 || $before_id > 0 || $since) {
                return Notice::getStreamDirect($qry, $offset, $limit, $since_id, $before_id, $order, $since);
            } else {
                return Notice::getCachedStream($qry, $cachekey, $offset, $limit, $order);
            }
        }

        return Notice::getStreamDirect($qry, $offset, $limit, $since_id, $before_id, $order, $since);
    }

    static function getStreamDirect($qry, $offset, $limit, $since_id, $before_id, $order, $since) {

        $needAnd = false;
        $needWhere = true;

        if (preg_match('/\bWHERE\b/i', $qry)) {
            $needWhere = false;
            $needAnd = true;
        }

        if ($since_id > 0) {

            if ($needWhere) {
                $qry .= ' WHERE ';
                $needWhere = false;
            } else {
                $qry .= ' AND ';
            }

            $qry .= ' notice.id > ' . $since_id;
        }

        if ($before_id > 0) {

            if ($needWhere) {
                $qry .= ' WHERE ';
                $needWhere = false;
            } else {
                $qry .= ' AND ';
            }

            $qry .= ' notice.id < ' . $before_id;
        }

        if ($since) {

            if ($needWhere) {
                $qry .= ' WHERE ';
                $needWhere = false;
            } else {
                $qry .= ' AND ';
            }

            $qry .= ' notice.created > \'' . date('Y-m-d H:i:s', $since) . '\'';
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

    # XXX: this is pretty long and should probably be broken up into
    # some helper functions

    static function getCachedStream($qry, $cachekey, $offset, $limit, $order) {

        # If outside our cache window, just go to the DB

        if ($offset + $limit > NOTICE_CACHE_WINDOW) {
            return Notice::getStreamDirect($qry, $offset, $limit, null, null, $order, null);
        }

        # Get the cache; if we can't, just go to the DB

        $cache = common_memcache();

        if (!$cache) {
            return Notice::getStreamDirect($qry, $offset, $limit, null, null, $order, null);
        }

        # Get the notices out of the cache

        $notices = $cache->get(common_cache_key($cachekey));

        # On a cache hit, return a DB-object-like wrapper

        if ($notices !== false) {
            $wrapper = new ArrayWrapper(array_slice($notices, $offset, $limit));
            return $wrapper;
        }

        # If the cache was invalidated because of new data being
        # added, we can try and just get the new stuff. We keep an additional
        # copy of the data at the key + ';last'

        # No cache hit. Try to get the *last* cached version

        $last_notices = $cache->get(common_cache_key($cachekey) . ';last');

        if ($last_notices) {

            # Reverse-chron order, so last ID is last.

            $last_id = $last_notices[0]->id;

            # XXX: this assumes monotonically increasing IDs; a fair
            # bet with our DB.

            $new_notice = Notice::getStreamDirect($qry, 0, NOTICE_CACHE_WINDOW,
                                                  $last_id, null, $order, null);

            if ($new_notice) {
                $new_notices = array();
                while ($new_notice->fetch()) {
                    $new_notices[] = clone($new_notice);
                }
                $new_notice->free();
                $notices = array_slice(array_merge($new_notices, $last_notices),
                                       0, NOTICE_CACHE_WINDOW);

                # Store the array in the cache for next time

                $result = $cache->set(common_cache_key($cachekey), $notices);
                $result = $cache->set(common_cache_key($cachekey) . ';last', $notices);

                # return a wrapper of the array for use now

                return new ArrayWrapper(array_slice($notices, $offset, $limit));
            }
        }

        # Otherwise, get the full cache window out of the DB

        $notice = Notice::getStreamDirect($qry, 0, NOTICE_CACHE_WINDOW, null, null, $order, null);

        # If there are no hits, just return the value

        if (!$notice) {
            return $notice;
        }

        # Pack results into an array

        $notices = array();

        while ($notice->fetch()) {
            $notices[] = clone($notice);
        }

        $notice->free();

        # Store the array in the cache for next time

        $result = $cache->set(common_cache_key($cachekey), $notices);
        $result = $cache->set(common_cache_key($cachekey) . ';last', $notices);

        # return a wrapper of the array for use now

        $wrapper = new ArrayWrapper(array_slice($notices, $offset, $limit));

        return $wrapper;
    }

    function publicStream($offset=0, $limit=20, $since_id=0, $before_id=0, $since=null)
    {

        $parts = array();

        $qry = 'SELECT * FROM notice ';

        if (common_config('public', 'localonly')) {
            $parts[] = 'is_local = 1';
        } else {
            # -1 == blacklisted
            $parts[] = 'is_local != -1';
        }

        if ($parts) {
            $qry .= ' WHERE ' . implode(' AND ', $parts);
        }

        return Notice::getStream($qry,
                                 'public',
                                 $offset, $limit, $since_id, $before_id, null, $since);
    }

    function addToInboxes()
    {
        $enabled = common_config('inboxes', 'enabled');

        if ($enabled === true || $enabled === 'transitional') {
            $inbox = new Notice_inbox();
            $UT = common_config('db','type')=='pgsql'?'"user"':'user';
            $qry = 'INSERT INTO notice_inbox (user_id, notice_id, created) ' .
              "SELECT $UT.id, " . $this->id . ", '" . $this->created . "' " .
              "FROM $UT JOIN subscription ON $UT.id = subscription.subscriber " .
              'WHERE subscription.subscribed = ' . $this->profile_id . ' ' .
              'AND NOT EXISTS (SELECT user_id, notice_id ' .
              'FROM notice_inbox ' .
              "WHERE user_id = $UT.id " .
              'AND notice_id = ' . $this->id . ' )';
            if ($enabled === 'transitional') {
                $qry .= " AND $UT.inboxed = 1";
            }
            $inbox->query($qry);
        }
        return;
    }

    function saveGroups()
    {
        $enabled = common_config('inboxes', 'enabled');
        if ($enabled !== true && $enabled !== 'transitional') {
            return;
        }

        /* extract all !group */
        $count = preg_match_all('/(?:^|\s)!([A-Za-z0-9]{1,64})/',
                                strtolower($this->content),
                                $match);
        if (!$count) {
            return true;
        }

        $profile = $this->getProfile();

        /* Add them to the database */

        foreach (array_unique($match[1]) as $nickname) {
            /* XXX: remote groups. */
            $group = User_group::staticGet('nickname', $nickname);

            if (!$group) {
                continue;
            }

            // we automatically add a tag for every group name, too

            $tag = Notice_tag::pkeyGet(array('tag' => common_canonical_tag($nickname),
                                           'notice_id' => $this->id));

            if (is_null($tag)) {
                $this->saveTag($nickname);
            }

            if ($profile->isMember($group)) {

                $gi = new Group_inbox();

                $gi->group_id  = $group->id;
                $gi->notice_id = $this->id;
                $gi->created   = common_sql_now();

                $result = $gi->insert();

                if (!$result) {
                    common_log_db_error($gi, 'INSERT', __FILE__);
                }

                // FIXME: do this in an offline daemon

                $inbox = new Notice_inbox();
                $UT = common_config('db','type')=='pgsql'?'"user"':'user';
                $qry = 'INSERT INTO notice_inbox (user_id, notice_id, created, source) ' .
                  "SELECT $UT.id, " . $this->id . ", '" . $this->created . "', 2 " .
                  "FROM $UT JOIN group_member ON $UT.id = group_member.profile_id " .
                  'WHERE group_member.group_id = ' . $group->id . ' ' .
                  'AND NOT EXISTS (SELECT user_id, notice_id ' .
                  'FROM notice_inbox ' .
                  "WHERE user_id = $UT.id " .
                  'AND notice_id = ' . $this->id . ' )';
                if ($enabled === 'transitional') {
                    $qry .= " AND $UT.inboxed = 1";
                }
                $result = $inbox->query($qry);
            }
        }
    }

    function saveReplies()
    {
        // Alternative reply format
        $tname = false;
        if (preg_match('/^T ([A-Z0-9]{1,64}) /', $this->content, $match)) {
            $tname = $match[1];
        }
        // extract all @messages
        $cnt = preg_match_all('/(?:^|\s)@([a-z0-9]{1,64})/', $this->content, $match);

        $names = array();

        if ($cnt || $tname) {
            // XXX: is there another way to make an array copy?
            $names = ($tname) ? array_unique(array_merge(array(strtolower($tname)), $match[1])) : array_unique($match[1]);
        }

        $sender = Profile::staticGet($this->profile_id);

        $replied = array();

        // store replied only for first @ (what user/notice what the reply directed,
        // we assume first @ is it)

        for ($i=0; $i<count($names); $i++) {
            $nickname = $names[$i];
            $recipient = common_relative_profile($sender, $nickname, $this->created);
            if (!$recipient) {
                continue;
            }
            if ($i == 0 && ($recipient->id != $sender->id) && !$this->reply_to) { // Don't save reply to self
                $reply_for = $recipient;
                $recipient_notice = $reply_for->getCurrentNotice();
                if ($recipient_notice) {
                    $orig = clone($this);
                    $this->reply_to = $recipient_notice->id;
                    $this->update($orig);
                }
            }
            // Don't save replies from blocked profile to local user
            $recipient_user = User::staticGet('id', $recipient->id);
            if ($recipient_user && $recipient_user->hasBlocked($sender)) {
                continue;
            }
            $reply = new Reply();
            $reply->notice_id = $this->id;
            $reply->profile_id = $recipient->id;
            $id = $reply->insert();
            if (!$id) {
                $last_error = &PEAR::getStaticProperty('DB_DataObject','lastError');
                common_log(LOG_ERR, 'DB error inserting reply: ' . $last_error->message);
                common_server_error(sprintf(_('DB error inserting reply: %s'), $last_error->message));
                return;
            } else {
                $replied[$recipient->id] = 1;
            }
        }

        // Hash format replies, too
        $cnt = preg_match_all('/(?:^|\s)@#([a-z0-9]{1,64})/', $this->content, $match);
        if ($cnt) {
            foreach ($match[1] as $tag) {
                $tagged = Profile_tag::getTagged($sender->id, $tag);
                foreach ($tagged as $t) {
                    if (!$replied[$t->id]) {
                        // Don't save replies from blocked profile to local user
                        $t_user = User::staticGet('id', $t->id);
                        if ($t_user && $t_user->hasBlocked($sender)) {
                            continue;
                        }
                        $reply = new Reply();
                        $reply->notice_id = $this->id;
                        $reply->profile_id = $t->id;
                        $id = $reply->insert();
                        if (!$id) {
                            common_log_db_error($reply, 'INSERT', __FILE__);
                            return;
                        } else {
                            $replied[$recipient->id] = 1;
                        }
                    }
                }
            }
        }

        foreach (array_keys($replied) as $recipient) {
            $user = User::staticGet('id', $recipient);
            if ($user) {
                mail_notify_attn($user, $this);
            }
        }
    }
}
