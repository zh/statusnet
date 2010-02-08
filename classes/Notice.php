<?php
/**
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2008, 2009, StatusNet, Inc.
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
 *
 * @category Notices
 * @package  StatusNet
 * @author   Brenda Wallace <shiny@cpan.org>
 * @author   Christopher Vollick <psycotica0@gmail.com>
 * @author   CiaranG <ciaran@ciarang.com>
 * @author   Craig Andrews <candrews@integralblue.com>
 * @author   Evan Prodromou <evan@controlezvous.ca>
 * @author   Gina Haeussge <osd@foosel.net>
 * @author   Jeffery To <jeffery.to@gmail.com>
 * @author   Mike Cochrane <mikec@mikenz.geek.nz>
 * @author   Robin Millette <millette@controlyourself.ca>
 * @author   Sarven Capadisli <csarven@controlyourself.ca>
 * @author   Tom Adams <tom@holizz.com>
 * @license  GNU Affero General Public License http://www.gnu.org/licenses/
 */

if (!defined('STATUSNET') && !defined('LACONICA')) {
    exit(1);
}

/**
 * Table Definition for notice
 */
require_once INSTALLDIR.'/classes/Memcached_DataObject.php';

/* We keep the first three 20-notice pages, plus one for pagination check,
 * in the memcached cache. */

define('NOTICE_CACHE_WINDOW', 61);

define('MAX_BOXCARS', 128);

class Notice extends Memcached_DataObject
{
    ###START_AUTOCODE
    /* the code below is auto generated do not remove the above tag */

    public $__table = 'notice';                          // table name
    public $id;                              // int(4)  primary_key not_null
    public $profile_id;                      // int(4)  multiple_key not_null
    public $uri;                             // varchar(255)  unique_key
    public $content;                         // text
    public $rendered;                        // text
    public $url;                             // varchar(255)
    public $created;                         // datetime  multiple_key not_null default_0000-00-00%2000%3A00%3A00
    public $modified;                        // timestamp   not_null default_CURRENT_TIMESTAMP
    public $reply_to;                        // int(4)
    public $is_local;                        // int(4)
    public $source;                          // varchar(32)
    public $conversation;                    // int(4)
    public $lat;                             // decimal(10,7)
    public $lon;                             // decimal(10,7)
    public $location_id;                     // int(4)
    public $location_ns;                     // int(4)
    public $repeat_of;                       // int(4)

    /* Static get */
    function staticGet($k,$v=NULL)
    {
        return Memcached_DataObject::staticGet('Notice',$k,$v);
    }

    /* the code above is auto generated do not remove the tag below */
    ###END_AUTOCODE

    /* Notice types */
    const LOCAL_PUBLIC    =  1;
    const REMOTE_OMB      =  0;
    const LOCAL_NONPUBLIC = -1;
    const GATEWAY         = -2;

    function getProfile()
    {
        return Profile::staticGet('id', $this->profile_id);
    }

    function delete()
    {
        // For auditing purposes, save a record that the notice
        // was deleted.

        $deleted = new Deleted_notice();

        $deleted->id         = $this->id;
        $deleted->profile_id = $this->profile_id;
        $deleted->uri        = $this->uri;
        $deleted->created    = $this->created;
        $deleted->deleted    = common_sql_now();

        $deleted->insert();

        // Clear related records

        $this->clearReplies();
        $this->clearRepeats();
        $this->clearFaves();
        $this->clearTags();
        $this->clearGroupInboxes();

        // NOTE: we don't clear inboxes
        // NOTE: we don't clear queue items

        $result = parent::delete();
    }

    function saveTags()
    {
        /* extract all #hastags */
        $count = preg_match_all('/(?:^|\s)#([\pL\pN_\-\.]{1,64})/', strtolower($this->content), $match);
        if (!$count) {
            return true;
        }

        //turn each into their canonical tag
        //this is needed to remove dupes before saving e.g. #hash.tag = #hashtag
        $hashtags = array();
        for($i=0; $i<count($match[1]); $i++) {
            $hashtags[] = common_canonical_tag($match[1][$i]);
        }

        /* Add them to the database */
        foreach(array_unique($hashtags) as $hashtag) {
            /* elide characters we don't want in the tag */
            $this->saveTag($hashtag);
            self::blow('profile:notice_ids_tagged:%d:%s', $this->profile_id, $hashtag);
        }
        return true;
    }

    function saveTag($hashtag)
    {
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

        // if it's saved, blow its cache
        $tag->blowCache(false);
    }

    /**
     * Save a new notice and push it out to subscribers' inboxes.
     * Poster's permissions are checked before sending.
     *
     * @param int $profile_id Profile ID of the poster
     * @param string $content source message text; links may be shortened
     *                        per current user's preference
     * @param string $source source key ('web', 'api', etc)
     * @param array $options Associative array of optional properties:
     *              string 'created' timestamp of notice; defaults to now
     *              int 'is_local' source/gateway ID, one of:
     *                  Notice::LOCAL_PUBLIC    - Local, ok to appear in public timeline
     *                  Notice::REMOTE_OMB      - Sent from a remote OMB service;
     *                                            hide from public timeline but show in
     *                                            local "and friends" timelines
     *                  Notice::LOCAL_NONPUBLIC - Local, but hide from public timeline
     *                  Notice::GATEWAY         - From another non-OMB service;
     *                                            will not appear in public views
     *              float 'lat' decimal latitude for geolocation
     *              float 'lon' decimal longitude for geolocation
     *              int 'location_id' geoname identifier
     *              int 'location_ns' geoname namespace to interpret location_id
     *              int 'reply_to'; notice ID this is a reply to
     *              int 'repeat_of'; notice ID this is a repeat of
     *              string 'uri' permalink to notice; defaults to local notice URL
     *
     * @return Notice
     * @throws ClientException
     */
    static function saveNew($profile_id, $content, $source, $options=null) {
        $defaults = array('uri' => null,
                          'reply_to' => null,
                          'repeat_of' => null);

        if (!empty($options)) {
            $options = $options + $defaults;
            extract($options);
        }

        if (!isset($is_local)) {
            $is_local = Notice::LOCAL_PUBLIC;
        }

        $profile = Profile::staticGet($profile_id);

        $final = common_shorten_links($content);

        if (Notice::contentTooLong($final)) {
            throw new ClientException(_('Problem saving notice. Too long.'));
        }

        if (empty($profile)) {
            throw new ClientException(_('Problem saving notice. Unknown user.'));
        }

        if (common_config('throttle', 'enabled') && !Notice::checkEditThrottle($profile_id)) {
            common_log(LOG_WARNING, 'Excessive posting by profile #' . $profile_id . '; throttled.');
            throw new ClientException(_('Too many notices too fast; take a breather '.
                                        'and post again in a few minutes.'));
        }

        if (common_config('site', 'dupelimit') > 0 && !Notice::checkDupes($profile_id, $final)) {
            common_log(LOG_WARNING, 'Dupe posting by profile #' . $profile_id . '; throttled.');
            throw new ClientException(_('Too many duplicate messages too quickly;'.
                                        ' take a breather and post again in a few minutes.'));
        }

        if (!$profile->hasRight(Right::NEWNOTICE)) {
            common_log(LOG_WARNING, "Attempted post from user disallowed to post: " . $profile->nickname);
            throw new ClientException(_('You are banned from posting notices on this site.'));
        }

        $notice = new Notice();
        $notice->profile_id = $profile_id;

        $autosource = common_config('public', 'autosource');

        # Sandboxed are non-false, but not 1, either

        if (!$profile->hasRight(Right::PUBLICNOTICE) ||
            ($source && $autosource && in_array($source, $autosource))) {
            $notice->is_local = Notice::LOCAL_NONPUBLIC;
        } else {
            $notice->is_local = $is_local;
        }

        if (!empty($created)) {
            $notice->created = $created;
        } else {
            $notice->created = common_sql_now();
        }

        $notice->content = $final;
        $notice->rendered = common_render_content($final, $notice);
        $notice->source = $source;
        $notice->uri = $uri;

        // Handle repeat case

        if (isset($repeat_of)) {
            $notice->repeat_of = $repeat_of;
        } else {
            $notice->reply_to = self::getReplyTo($reply_to, $profile_id, $source, $final);
        }

        if (!empty($notice->reply_to)) {
            $reply = Notice::staticGet('id', $notice->reply_to);
            $notice->conversation = $reply->conversation;
        }

        if (!empty($lat) && !empty($lon)) {
            $notice->lat = $lat;
            $notice->lon = $lon;
        }

        if (!empty($location_ns) && !empty($location_id)) {
            $notice->location_id = $location_id;
            $notice->location_ns = $location_ns;
        }

        if (Event::handle('StartNoticeSave', array(&$notice))) {

            // XXX: some of these functions write to the DB

            $id = $notice->insert();

            if (!$id) {
                common_log_db_error($notice, 'INSERT', __FILE__);
                throw new ServerException(_('Problem saving notice.'));
            }

            // Update ID-dependent columns: URI, conversation

            $orig = clone($notice);

            $changed = false;

            if (empty($uri)) {
                $notice->uri = common_notice_uri($notice);
                $changed = true;
            }

            // If it's not part of a conversation, it's
            // the beginning of a new conversation.

            if (empty($notice->conversation)) {
                $notice->conversation = $notice->id;
                $changed = true;
            }

            if ($changed) {
                if (!$notice->update($orig)) {
                    common_log_db_error($notice, 'UPDATE', __FILE__);
                    throw new ServerException(_('Problem saving notice.'));
                }
            }

        }

        # Clear the cache for subscribed users, so they'll update at next request
        # XXX: someone clever could prepend instead of clearing the cache
        $notice->blowOnInsert();

        $notice->distribute();

        return $notice;
    }

    function blowOnInsert()
    {
        self::blow('profile:notice_ids:%d', $this->profile_id);
        self::blow('public');

        if ($this->conversation != $this->id) {
            self::blow('notice:conversation_ids:%d', $this->conversation);
        }

        if (!empty($this->repeat_of)) {
            self::blow('notice:repeats:%d', $this->repeat_of);
        }

        $original = Notice::staticGet('id', $this->repeat_of);

        if (!empty($original)) {
            $originalUser = User::staticGet('id', $original->profile_id);
            if (!empty($originalUser)) {
                self::blow('user:repeats_of_me:%d', $originalUser->id);
            }
        }

        $profile = Profile::staticGet($this->profile_id);
        $profile->blowNoticeCount();
    }

    /** save all urls in the notice to the db
     *
     * follow redirects and save all available file information
     * (mimetype, date, size, oembed, etc.)
     *
     * @return void
     */
    function saveUrls() {
        common_replace_urls_callback($this->content, array($this, 'saveUrl'), $this->id);
    }

    function saveUrl($data) {
        list($url, $notice_id) = $data;
        File::processNew($url, $notice_id);
    }

    static function checkDupes($profile_id, $content) {
        $profile = Profile::staticGet($profile_id);
        if (empty($profile)) {
            return false;
        }
        $notice = $profile->getNotices(0, NOTICE_CACHE_WINDOW);
        if (!empty($notice)) {
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
        if (empty($profile)) {
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

    function getUploadedAttachment() {
        $post = clone $this;
        $query = 'select file.url as up, file.id as i from file join file_to_post on file.id = file_id where post_id=' . $post->escape($post->id) . ' and url like "%/notice/%/file"';
        $post->query($query);
        $post->fetch();
        if (empty($post->up) || empty($post->i)) {
            $ret = false;
        } else {
            $ret = array($post->up, $post->i);
        }
        $post->free();
        return $ret;
    }

    function hasAttachments() {
        $post = clone $this;
        $query = "select count(file_id) as n_attachments from file join file_to_post on (file_id = file.id) join notice on (post_id = notice.id) where post_id = " . $post->escape($post->id);
        $post->query($query);
        $post->fetch();
        $n_attachments = intval($post->n_attachments);
        $post->free();
        return $n_attachments;
    }

    function attachments() {
        // XXX: cache this
        $att = array();
        $f2p = new File_to_post;
        $f2p->post_id = $this->id;
        if ($f2p->find()) {
            while ($f2p->fetch()) {
                $f = File::staticGet($f2p->file_id);
                $att[] = clone($f);
            }
        }
        return $att;
    }

    function getStreamByIds($ids)
    {
        $cache = common_memcache();

        if (!empty($cache)) {
            $notices = array();
            foreach ($ids as $id) {
                $n = Notice::staticGet('id', $id);
                if (!empty($n)) {
                    $notices[] = $n;
                }
            }
            return new ArrayWrapper($notices);
        } else {
            $notice = new Notice();
            if (empty($ids)) {
                //if no IDs requested, just return the notice object
                return $notice;
            }
            $notice->whereAdd('id in (' . implode(', ', $ids) . ')');

            $notice->find();

            $temp = array();

            while ($notice->fetch()) {
                $temp[$notice->id] = clone($notice);
            }

            $wrapped = array();

            foreach ($ids as $id) {
                if (array_key_exists($id, $temp)) {
                    $wrapped[] = $temp[$id];
                }
            }

            return new ArrayWrapper($wrapped);
        }
    }

    function publicStream($offset=0, $limit=20, $since_id=0, $max_id=0, $since=null)
    {
        $ids = Notice::stream(array('Notice', '_publicStreamDirect'),
                              array(),
                              'public',
                              $offset, $limit, $since_id, $max_id, $since);

        return Notice::getStreamByIds($ids);
    }

    function _publicStreamDirect($offset=0, $limit=20, $since_id=0, $max_id=0, $since=null)
    {
        $notice = new Notice();

        $notice->selectAdd(); // clears it
        $notice->selectAdd('id');

        $notice->orderBy('id DESC');

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        if (common_config('public', 'localonly')) {
            $notice->whereAdd('is_local = ' . Notice::LOCAL_PUBLIC);
        } else {
            # -1 == blacklisted, -2 == gateway (i.e. Twitter)
            $notice->whereAdd('is_local !='. Notice::LOCAL_NONPUBLIC);
            $notice->whereAdd('is_local !='. Notice::GATEWAY);
        }

        if ($since_id != 0) {
            $notice->whereAdd('id > ' . $since_id);
        }

        if ($max_id != 0) {
            $notice->whereAdd('id <= ' . $max_id);
        }

        if (!is_null($since)) {
            $notice->whereAdd('created > \'' . date('Y-m-d H:i:s', $since) . '\'');
        }

        $ids = array();

        if ($notice->find()) {
            while ($notice->fetch()) {
                $ids[] = $notice->id;
            }
        }

        $notice->free();
        $notice = NULL;

        return $ids;
    }

    function conversationStream($id, $offset=0, $limit=20, $since_id=0, $max_id=0, $since=null)
    {
        $ids = Notice::stream(array('Notice', '_conversationStreamDirect'),
                              array($id),
                              'notice:conversation_ids:'.$id,
                              $offset, $limit, $since_id, $max_id, $since);

        return Notice::getStreamByIds($ids);
    }

    function _conversationStreamDirect($id, $offset=0, $limit=20, $since_id=0, $max_id=0, $since=null)
    {
        $notice = new Notice();

        $notice->selectAdd(); // clears it
        $notice->selectAdd('id');

        $notice->conversation = $id;

        $notice->orderBy('id DESC');

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        if ($since_id != 0) {
            $notice->whereAdd('id > ' . $since_id);
        }

        if ($max_id != 0) {
            $notice->whereAdd('id <= ' . $max_id);
        }

        if (!is_null($since)) {
            $notice->whereAdd('created > \'' . date('Y-m-d H:i:s', $since) . '\'');
        }

        $ids = array();

        if ($notice->find()) {
            while ($notice->fetch()) {
                $ids[] = $notice->id;
            }
        }

        $notice->free();
        $notice = NULL;

        return $ids;
    }

    /**
     * @param $groups array of Group *objects*
     * @param $recipients array of profile *ids*
     */
    function whoGets($groups=null, $recipients=null)
    {
        $c = self::memcache();

        if (!empty($c)) {
            $ni = $c->get(common_cache_key('notice:who_gets:'.$this->id));
            if ($ni !== false) {
                return $ni;
            }
        }

        if (is_null($groups)) {
            $groups = $this->getGroups();
        }

        if (is_null($recipients)) {
            $recipients = $this->getReplies();
        }

        $users = $this->getSubscribedUsers();

        // FIXME: kind of ignoring 'transitional'...
        // we'll probably stop supporting inboxless mode
        // in 0.9.x

        $ni = array();

        foreach ($users as $id) {
            $ni[$id] = NOTICE_INBOX_SOURCE_SUB;
        }

        $profile = $this->getProfile();

        foreach ($groups as $group) {
            $users = $group->getUserMembers();
            foreach ($users as $id) {
                if (!array_key_exists($id, $ni)) {
                    $user = User::staticGet('id', $id);
                    if (!$user->hasBlocked($profile)) {
                        $ni[$id] = NOTICE_INBOX_SOURCE_GROUP;
                    }
                }
            }
        }

        foreach ($recipients as $recipient) {

            if (!array_key_exists($recipient, $ni)) {
                $recipientUser = User::staticGet('id', $recipient);
                if (!empty($recipientUser)) {
                    $ni[$recipient] = NOTICE_INBOX_SOURCE_REPLY;
                }
            }
        }

        if (!empty($c)) {
            // XXX: pack this data better
            $c->set(common_cache_key('notice:who_gets:'.$this->id), $ni);
        }

        return $ni;
    }

    function addToInboxes($groups, $recipients)
    {
        $ni = $this->whoGets($groups, $recipients);

        Inbox::bulkInsert($this->id, array_keys($ni));

        return;
    }

    function getSubscribedUsers()
    {
        $user = new User();

        if(common_config('db','quote_identifiers'))
          $user_table = '"user"';
        else $user_table = 'user';

        $qry =
          'SELECT id ' .
          'FROM '. $user_table .' JOIN subscription '.
          'ON '. $user_table .'.id = subscription.subscriber ' .
          'WHERE subscription.subscribed = %d ';

        $user->query(sprintf($qry, $this->profile_id));

        $ids = array();

        while ($user->fetch()) {
            $ids[] = $user->id;
        }

        $user->free();

        return $ids;
    }

    /**
     * @return array of Group objects
     */
    function saveGroups()
    {
        // Don't save groups for repeats

        if (!empty($this->repeat_of)) {
            return array();
        }

        $groups = array();

        /* extract all !group */
        $count = preg_match_all('/(?:^|\s)!([A-Za-z0-9]{1,64})/',
                                strtolower($this->content),
                                $match);
        if (!$count) {
            return $groups;
        }

        $profile = $this->getProfile();

        /* Add them to the database */

        foreach (array_unique($match[1]) as $nickname) {
            /* XXX: remote groups. */
            $group = User_group::getForNickname($nickname);

            if (empty($group)) {
                continue;
            }

            // we automatically add a tag for every group name, too

            $tag = Notice_tag::pkeyGet(array('tag' => common_canonical_tag($nickname),
                                             'notice_id' => $this->id));

            if (is_null($tag)) {
                $this->saveTag($nickname);
            }

            if ($profile->isMember($group)) {

                $result = $this->addToGroupInbox($group);

                if (!$result) {
                    common_log_db_error($gi, 'INSERT', __FILE__);
                }

                $groups[] = clone($group);
            }
        }

        return $groups;
    }

    function addToGroupInbox($group)
    {
        $gi = Group_inbox::pkeyGet(array('group_id' => $group->id,
                                         'notice_id' => $this->id));

        if (empty($gi)) {

            $gi = new Group_inbox();

            $gi->group_id  = $group->id;
            $gi->notice_id = $this->id;
            $gi->created   = $this->created;

            $result = $gi->insert();

            if (!result) {
                common_log_db_error($gi, 'INSERT', __FILE__);
                throw new ServerException(_('Problem saving group inbox.'));
            }

            self::blow('user_group:notice_ids:%d', $gi->group_id);
        }

        return true;
    }

    /**
     * @return array of integer profile IDs
     */
    function saveReplies()
    {
        // Don't save reply data for repeats

        if (!empty($this->repeat_of)) {
            return array();
        }

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
            if (empty($recipient)) {
                continue;
            }
            // Don't save replies from blocked profile to local user
            $recipient_user = User::staticGet('id', $recipient->id);
            if (!empty($recipient_user) && $recipient_user->hasBlocked($sender)) {
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
                return array();
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
                            return array();
                        } else {
                            $replied[$recipient->id] = 1;
                        }
                    }
                }
            }
        }

        $recipientIds = array_keys($replied);

        foreach ($recipientIds as $recipientId) {
            $user = User::staticGet('id', $recipientId);
            if (!empty($user)) {
                self::blow('reply:stream:%d', $reply->profile_id);
                mail_notify_attn($user, $this);
            }
        }

        return $recipientIds;
    }

    function getReplies()
    {
        // XXX: cache me

        $ids = array();

        $reply = new Reply();
        $reply->selectAdd();
        $reply->selectAdd('profile_id');
        $reply->notice_id = $this->id;

        if ($reply->find()) {
            while($reply->fetch()) {
                $ids[] = $reply->profile_id;
            }
        }

        $reply->free();

        return $ids;
    }

    /**
     * Same calculation as saveGroups but without the saving
     * @fixme merge the functions
     * @return array of Group objects
     */
    function getGroups()
    {
        // Don't save groups for repeats

        if (!empty($this->repeat_of)) {
            return array();
        }

        // XXX: cache me

        $groups = array();

        $gi = new Group_inbox();

        $gi->selectAdd();
        $gi->selectAdd('group_id');

        $gi->notice_id = $this->id;

        if ($gi->find()) {
            while ($gi->fetch()) {
                $groups[] = clone($gi);
            }
        }

        $gi->free();

        return $groups;
    }

    function asAtomEntry($namespace=false, $source=false)
    {
        $profile = $this->getProfile();

        $xs = new XMLStringer(true);

        if ($namespace) {
            $attrs = array('xmlns' => 'http://www.w3.org/2005/Atom',
                           'xmlns:thr' => 'http://purl.org/syndication/thread/1.0');
        } else {
            $attrs = array();
        }

        $xs->elementStart('entry', $attrs);

        if ($source) {
            $xs->elementStart('source');
            $xs->element('title', null, $profile->nickname . " - " . common_config('site', 'name'));
            $xs->element('link', array('href' => $profile->profileurl));
            $user = User::staticGet('id', $profile->id);
            if (!empty($user)) {
                $atom_feed = common_local_url('ApiTimelineUser',
                                              array('format' => 'atom',
                                                    'id' => $profile->nickname));
                $xs->element('link', array('rel' => 'self',
                                           'type' => 'application/atom+xml',
                                           'href' => $profile->profileurl));
                $xs->element('link', array('rel' => 'license',
                                           'href' => common_config('license', 'url')));
            }

            $xs->element('icon', null, $profile->avatarUrl(AVATAR_PROFILE_SIZE));
        }

        $xs->elementStart('author');
        $xs->element('name', null, $profile->nickname);
        $xs->element('uri', null, $profile->profileurl);
        $xs->elementEnd('author');

        if ($source) {
            $xs->elementEnd('source');
        }

        $xs->element('title', null, $this->content);
        $xs->element('summary', null, $this->content);

        $xs->element('link', array('rel' => 'alternate',
                                   'href' => $this->bestUrl()));

        $xs->element('id', null, $this->uri);

        $xs->element('published', null, common_date_w3dtf($this->created));
        $xs->element('updated', null, common_date_w3dtf($this->created));

        if ($this->reply_to) {
            $reply_notice = Notice::staticGet('id', $this->reply_to);
            if (!empty($reply_notice)) {
                $xs->element('link', array('rel' => 'related',
                                           'href' => $reply_notice->bestUrl()));
                $xs->element('thr:in-reply-to',
                             array('ref' => $reply_notice->uri,
                                   'href' => $reply_notice->bestUrl()));
            }
        }

        $xs->element('content', array('type' => 'html'), $this->rendered);

        $tag = new Notice_tag();
        $tag->notice_id = $this->id;
        if ($tag->find()) {
            while ($tag->fetch()) {
                $xs->element('category', array('term' => $tag->tag));
            }
        }
        $tag->free();

        # Enclosures
        $attachments = $this->attachments();
        if($attachments){
            foreach($attachments as $attachment){
                $enclosure=$attachment->getEnclosure();
                if ($enclosure) {
                    $attributes = array('rel'=>'enclosure','href'=>$enclosure->url,'type'=>$enclosure->mimetype,'length'=>$enclosure->size);
                    if($enclosure->title){
                        $attributes['title']=$enclosure->title;
                    }
                    $xs->element('link', $attributes, null);
                }
            }
        }

        if (!empty($this->lat) && !empty($this->lon)) {
            $xs->elementStart('geo', array('xmlns:georss' => 'http://www.georss.org/georss'));
            $xs->element('georss:point', null, $this->lat . ' ' . $this->lon);
            $xs->elementEnd('geo');
        }

        $xs->elementEnd('entry');

        return $xs->getString();
    }

    function bestUrl()
    {
        if (!empty($this->url)) {
            return $this->url;
        } else if (!empty($this->uri) && preg_match('/^https?:/', $this->uri)) {
            return $this->uri;
        } else {
            return common_local_url('shownotice',
                                    array('notice' => $this->id));
        }
    }

    function stream($fn, $args, $cachekey, $offset=0, $limit=20, $since_id=0, $max_id=0, $since=null)
    {
        $cache = common_memcache();

        if (empty($cache) ||
            $since_id != 0 || $max_id != 0 || (!is_null($since) && $since > 0) ||
            is_null($limit) ||
            ($offset + $limit) > NOTICE_CACHE_WINDOW) {
            return call_user_func_array($fn, array_merge($args, array($offset, $limit, $since_id,
                                                                      $max_id, $since)));
        }

        $idkey = common_cache_key($cachekey);

        $idstr = $cache->get($idkey);

        if ($idstr !== false) {
            // Cache hit! Woohoo!
            $window = explode(',', $idstr);
            $ids = array_slice($window, $offset, $limit);
            return $ids;
        }

        $laststr = $cache->get($idkey.';last');

        if ($laststr !== false) {
            $window = explode(',', $laststr);
            $last_id = $window[0];
            $new_ids = call_user_func_array($fn, array_merge($args, array(0, NOTICE_CACHE_WINDOW,
                                                                          $last_id, 0, null)));

            $new_window = array_merge($new_ids, $window);

            $new_windowstr = implode(',', $new_window);

            $result = $cache->set($idkey, $new_windowstr);
            $result = $cache->set($idkey . ';last', $new_windowstr);

            $ids = array_slice($new_window, $offset, $limit);

            return $ids;
        }

        $window = call_user_func_array($fn, array_merge($args, array(0, NOTICE_CACHE_WINDOW,
                                                                     0, 0, null)));

        $windowstr = implode(',', $window);

        $result = $cache->set($idkey, $windowstr);
        $result = $cache->set($idkey . ';last', $windowstr);

        $ids = array_slice($window, $offset, $limit);

        return $ids;
    }

    /**
     * Determine which notice, if any, a new notice is in reply to.
     *
     * For conversation tracking, we try to see where this notice fits
     * in the tree. Rough algorithm is:
     *
     * if (reply_to is set and valid) {
     *     return reply_to;
     * } else if ((source not API or Web) and (content starts with "T NAME" or "@name ")) {
     *     return ID of last notice by initial @name in content;
     * }
     *
     * Note that all @nickname instances will still be used to save "reply" records,
     * so the notice shows up in the mentioned users' "replies" tab.
     *
     * @param integer $reply_to   ID passed in by Web or API
     * @param integer $profile_id ID of author
     * @param string  $source     Source tag, like 'web' or 'gwibber'
     * @param string  $content    Final notice content
     *
     * @return integer ID of replied-to notice, or null for not a reply.
     */

    static function getReplyTo($reply_to, $profile_id, $source, $content)
    {
        static $lb = array('xmpp', 'mail', 'sms', 'omb');

        // If $reply_to is specified, we check that it exists, and then
        // return it if it does

        if (!empty($reply_to)) {
            $reply_notice = Notice::staticGet('id', $reply_to);
            if (!empty($reply_notice)) {
                return $reply_to;
            }
        }

        // If it's not a "low bandwidth" source (one where you can't set
        // a reply_to argument), we return. This is mostly web and API
        // clients.

        if (!in_array($source, $lb)) {
            return null;
        }

        // Is there an initial @ or T?

        if (preg_match('/^T ([A-Z0-9]{1,64}) /', $content, $match) ||
            preg_match('/^@([a-z0-9]{1,64})\s+/', $content, $match)) {
            $nickname = common_canonical_nickname($match[1]);
        } else {
            return null;
        }

        // Figure out who that is.

        $sender = Profile::staticGet('id', $profile_id);
        if (empty($sender)) {
            return null;
        }

        $recipient = common_relative_profile($sender, $nickname, common_sql_now());

        if (empty($recipient)) {
            return null;
        }

        // Get their last notice

        $last = $recipient->getCurrentNotice();

        if (!empty($last)) {
            return $last->id;
        }
    }

    static function maxContent()
    {
        $contentlimit = common_config('notice', 'contentlimit');
        // null => use global limit (distinct from 0!)
        if (is_null($contentlimit)) {
            $contentlimit = common_config('site', 'textlimit');
        }
        return $contentlimit;
    }

    static function contentTooLong($content)
    {
        $contentlimit = self::maxContent();
        return ($contentlimit > 0 && !empty($content) && (mb_strlen($content) > $contentlimit));
    }

    function getLocation()
    {
        $location = null;

        if (!empty($this->location_id) && !empty($this->location_ns)) {
            $location = Location::fromId($this->location_id, $this->location_ns);
        }

        if (is_null($location)) { // no ID, or Location::fromId() failed
            if (!empty($this->lat) && !empty($this->lon)) {
                $location = Location::fromLatLon($this->lat, $this->lon);
            }
        }

        return $location;
    }

    function repeat($repeater_id, $source)
    {
        $author = Profile::staticGet('id', $this->profile_id);

        $content = sprintf(_('RT @%1$s %2$s'),
                           $author->nickname,
                           $this->content);

        $maxlen = common_config('site', 'textlimit');
        if ($maxlen > 0 && mb_strlen($content) > $maxlen) {
            // Web interface and current Twitter API clients will
            // pull the original notice's text, but some older
            // clients and RSS/Atom feeds will see this trimmed text.
            //
            // Unfortunately this is likely to lose tags or URLs
            // at the end of long notices.
            $content = mb_substr($content, 0, $maxlen - 4) . ' ...';
        }

        return self::saveNew($repeater_id, $content, $source,
                             array('repeat_of' => $this->id));
    }

    // These are supposed to be in chron order!

    function repeatStream($limit=100)
    {
        $cache = common_memcache();

        if (empty($cache)) {
            $ids = $this->_repeatStreamDirect($limit);
        } else {
            $idstr = $cache->get(common_cache_key('notice:repeats:'.$this->id));
            if ($idstr !== false) {
                $ids = explode(',', $idstr);
            } else {
                $ids = $this->_repeatStreamDirect(100);
                $cache->set(common_cache_key('notice:repeats:'.$this->id), implode(',', $ids));
            }
            if ($limit < 100) {
                // We do a max of 100, so slice down to limit
                $ids = array_slice($ids, 0, $limit);
            }
        }

        return Notice::getStreamByIds($ids);
    }

    function _repeatStreamDirect($limit)
    {
        $notice = new Notice();

        $notice->selectAdd(); // clears it
        $notice->selectAdd('id');

        $notice->repeat_of = $this->id;

        $notice->orderBy('created'); // NB: asc!

        if (!is_null($offset)) {
            $notice->limit($offset, $limit);
        }

        $ids = array();

        if ($notice->find()) {
            while ($notice->fetch()) {
                $ids[] = $notice->id;
            }
        }

        $notice->free();
        $notice = NULL;

        return $ids;
    }

    function locationOptions($lat, $lon, $location_id, $location_ns, $profile = null)
    {
        $options = array();

        if (!empty($location_id) && !empty($location_ns)) {

            $options['location_id'] = $location_id;
            $options['location_ns'] = $location_ns;

            $location = Location::fromId($location_id, $location_ns);

            if (!empty($location)) {
                $options['lat'] = $location->lat;
                $options['lon'] = $location->lon;
            }

        } else if (!empty($lat) && !empty($lon)) {

            $options['lat'] = $lat;
            $options['lon'] = $lon;

            $location = Location::fromLatLon($lat, $lon);

            if (!empty($location)) {
                $options['location_id'] = $location->location_id;
                $options['location_ns'] = $location->location_ns;
            }
        } else if (!empty($profile)) {

            if (isset($profile->lat) && isset($profile->lon)) {
                $options['lat'] = $profile->lat;
                $options['lon'] = $profile->lon;
            }

            if (isset($profile->location_id) && isset($profile->location_ns)) {
                $options['location_id'] = $profile->location_id;
                $options['location_ns'] = $profile->location_ns;
            }
        }

        return $options;
    }

    function clearReplies()
    {
        $replyNotice = new Notice();
        $replyNotice->reply_to = $this->id;

        //Null any notices that are replies to this notice

        if ($replyNotice->find()) {
            while ($replyNotice->fetch()) {
                $orig = clone($replyNotice);
                $replyNotice->reply_to = null;
                $replyNotice->update($orig);
            }
        }

        // Reply records

        $reply = new Reply();
        $reply->notice_id = $this->id;

        if ($reply->find()) {
            while($reply->fetch()) {
                self::blow('reply:stream:%d', $reply->profile_id);
                $reply->delete();
            }
        }

        $reply->free();
    }

    function clearRepeats()
    {
        $repeatNotice = new Notice();
        $repeatNotice->repeat_of = $this->id;

        //Null any notices that are repeats of this notice

        if ($repeatNotice->find()) {
            while ($repeatNotice->fetch()) {
                $orig = clone($repeatNotice);
                $repeatNotice->repeat_of = null;
                $repeatNotice->update($orig);
            }
        }
    }

    function clearFaves()
    {
        $fave = new Fave();
        $fave->notice_id = $this->id;

        if ($fave->find()) {
            while ($fave->fetch()) {
                self::blow('fave:ids_by_user_own:%d', $fave->user_id);
                self::blow('fave:ids_by_user_own:%d;last', $fave->user_id);
                self::blow('fave:ids_by_user:%d', $fave->user_id);
                self::blow('fave:ids_by_user:%d;last', $fave->user_id);
                $fave->delete();
            }
        }

        $fave->free();
    }

    function clearTags()
    {
        $tag = new Notice_tag();
        $tag->notice_id = $this->id;

        if ($tag->find()) {
            while ($tag->fetch()) {
                self::blow('profile:notice_ids_tagged:%d:%s', $this->profile_id, common_keyize($tag->tag));
                self::blow('profile:notice_ids_tagged:%d:%s;last', $this->profile_id, common_keyize($tag->tag));
                self::blow('notice_tag:notice_ids:%s', common_keyize($tag->tag));
                self::blow('notice_tag:notice_ids:%s;last', common_keyize($tag->tag));
                $tag->delete();
            }
        }

        $tag->free();
    }

    function clearGroupInboxes()
    {
        $gi = new Group_inbox();

        $gi->notice_id = $this->id;

        if ($gi->find()) {
            while ($gi->fetch()) {
                self::blow('user_group:notice_ids:%d', $gi->group_id);
                $gi->delete();
            }
        }

        $gi->free();
    }

    function distribute()
    {
        if (common_config('queue', 'inboxes')) {
            // If there's a failure, we want to _force_
            // distribution at this point.
            try {
                $qm = QueueManager::get();
                $qm->enqueue($this, 'distrib');
            } catch (Exception $e) {
                // If the exception isn't transient, this
                // may throw more exceptions as DQH does
                // its own enqueueing. So, we ignore them!
                try {
                    $handler = new DistribQueueHandler();
                    $handler->handle($this);
                } catch (Exception $e) {
                    common_log(LOG_ERR, "emergency redistribution resulted in " . $e->getMessage());
                }
                // Re-throw so somebody smarter can handle it.
                throw $e;
            }
        } else {
            $handler = new DistribQueueHandler();
            $handler->handle($this);
        }
    }

    function insert()
    {
        $result = parent::insert();

        if ($result) {
            // Profile::hasRepeated() abuses pkeyGet(), so we
            // have to clear manually
            if (!empty($this->repeat_of)) {
                $c = self::memcache();
                if (!empty($c)) {
                    $ck = self::multicacheKey('Notice',
                                              array('profile_id' => $this->profile_id,
                                                    'repeat_of' => $this->repeat_of));
                    $c->delete($ck);
                }
            }
        }

        return $result;
    }
}
