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

class TwitterapiAction extends Action
{

    var $auth_user;

    /**
     * Initialization.
     *
     * @param array $args Web and URL arguments
     *
     * @return boolean false if user doesn't exist
     */

    function prepare($args)
    {
        parent::prepare($args);
        return true;
    }

    /**
     * Handle a request
     *
     * @param array $args Arguments from $_REQUEST
     *
     * @return void
     */

    function handle($args)
    {
        parent::handle($args);
    }

    /**
     * Overrides XMLOutputter::element to write booleans as strings (true|false).
     * See that method's documentation for more info.
     *
     * @param string $tag     Element type or tagname
     * @param array  $attrs   Array of element attributes, as
     *                        key-value pairs
     * @param string $content string content of the element
     *
     * @return void
     */
    function element($tag, $attrs=null, $content=null)
    {
        if (is_bool($content)) {
            $content = ($content ? 'true' : 'false');
        }

        return parent::element($tag, $attrs, $content);
    }

    function twitter_user_array($profile, $get_notice=false)
    {
        $twitter_user = array();

        $twitter_user['id'] = intval($profile->id);
        $twitter_user['name'] = $profile->getBestName();
        $twitter_user['screen_name'] = $profile->nickname;
        $twitter_user['location'] = ($profile->location) ? $profile->location : null;
        $twitter_user['description'] = ($profile->bio) ? $profile->bio : null;

        $avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
        $twitter_user['profile_image_url'] = ($avatar) ? $avatar->displayUrl() :
            Avatar::defaultImage(AVATAR_STREAM_SIZE);

        $twitter_user['url'] = ($profile->homepage) ? $profile->homepage : null;
        $twitter_user['protected'] = false; # not supported by Laconica yet
        $twitter_user['followers_count'] = $this->count_subscriptions($profile);

        // To be supported soon...
        $twitter_user['profile_background_color'] = '';
        $twitter_user['profile_text_color'] = '';
        $twitter_user['profile_link_color'] = '';
        $twitter_user['profile_sidebar_fill_color'] = '';
        $twitter_user['profile_sidebar_border_color'] = '';

        $subbed = DB_DataObject::factory('subscription');
        $subbed->subscriber = $profile->id;
        $subbed_count = (int) $subbed->count() - 1;
        $twitter_user['friends_count'] = (is_int($subbed_count)) ? $subbed_count : 0;

        $twitter_user['created_at'] = $this->date_twitter($profile->created);

        $faves = DB_DataObject::factory('fave');
        $faves->user_id = $user->id;
        $faves_count = (int) $faves->count();
        $twitter_user['favourites_count'] = $faves_count; // British spelling!

        // Need to pull up the user for some of this
        $user = User::staticGet($profile->id);

        $timezone = 'UTC';

        if ($user->timezone) {
            $timezone = $user->timezone;
        }

        $t = new DateTime;
        $t->setTimezone(new DateTimeZone($timezone));

        $twitter_user['utc_offset'] = $t->format('Z');
        $twitter_user['time_zone'] = $timezone;

        // To be supported some day, perhaps
        $twitter_user['profile_background_image_url'] = '';
        $twitter_user['profile_background_tile'] = false;

        $notices = DB_DataObject::factory('notice');
        $notices->profile_id = $profile->id;
        $notice_count = (int) $notices->count();

        $twitter_user['statuses_count'] = (is_int($notice_count)) ? $notice_count : 0;

        // Is the requesting user following this user?
        $twitter_user['following'] = false;
        $twitter_user['notifications'] = false;

        if (isset($apidata['user'])) {

            $twitter_user['following'] = $apidata['user']->isSubscribed($profile);

            // Notifications on?
            $sub = Subscription::pkeyGet(array('subscriber' =>
                $apidata['user']->id, 'subscribed' => $profile->id));

            if ($sub) {
                $twitter_user['notifications'] = ($sub->jabber || $sub->sms);
            }
        }

        if ($get_notice) {
            $notice = $profile->getCurrentNotice();
            if ($notice) {
                # don't get user!
                $twitter_user['status'] = $this->twitter_status_array($notice, false);
            }
        }

        return $twitter_user;
    }

    function twitter_status_array($notice, $include_user=true)
    {
        $profile = $notice->getProfile();

        $twitter_status = array();
        $twitter_status['text'] = $notice->content;
        $twitter_status['truncated'] = false; # Not possible on Laconica
        $twitter_status['created_at'] = $this->date_twitter($notice->created);
        $twitter_status['in_reply_to_status_id'] = ($notice->reply_to) ?
            intval($notice->reply_to) : null;
        $twitter_status['source'] = $this->source_link($notice->source);
        $twitter_status['id'] = intval($notice->id);

        $replier_profile = null;

        if ($notice->reply_to) {
            $reply = Notice::staticGet(intval($notice->reply_to));
            if ($reply) {
                $replier_profile = $reply->getProfile();
            }
        }

        $twitter_status['in_reply_to_user_id'] =
            ($replier_profile) ? intval($replier_profile->id) : null;
        $twitter_status['in_reply_to_screen_name'] =
            ($replier_profile) ? $replier_profile->nickname : null;

        if (isset($this->auth_user)) {
            $twitter_status['favorited'] = $this->auth_user->hasFave($notice);
        } else {
            $twitter_status['favorited'] = false;
        }

        if ($include_user) {
            # Don't get notice (recursive!)
            $twitter_user = $this->twitter_user_array($profile, false);
            $twitter_status['user'] = $twitter_user;
        }

        return $twitter_status;
    }

    function twitter_rss_entry_array($notice)
    {

        $profile = $notice->getProfile();
        $entry = array();

        # We trim() to avoid extraneous whitespace in the output

        $entry['content'] = common_xml_safe_str(trim($notice->rendered));
        $entry['title'] = $profile->nickname . ': ' . common_xml_safe_str(trim($notice->content));
        $entry['link'] = common_local_url('shownotice', array('notice' => $notice->id));
        $entry['published'] = common_date_iso8601($notice->created);

        $taguribase = common_config('integration', 'taguri');
        $entry['id'] = "tag:$taguribase:$entry[link]";

        $entry['updated'] = $entry['published'];
        $entry['author'] = $profile->getBestName();

        # RSS Item specific
        $entry['description'] = $entry['content'];
        $entry['pubDate'] = common_date_rfc2822($notice->created);
        $entry['guid'] = $entry['link'];

        return $entry;
    }

    function twitter_rss_dmsg_array($message)
    {

        $entry = array();

        $entry['title'] = sprintf('Message from %s to %s',
            $message->getFrom()->nickname, $message->getTo()->nickname);

        $entry['content'] = common_xml_safe_str(trim($message->content));
        $entry['link'] = common_local_url('showmessage', array('message' => $message->id));
        $entry['published'] = common_date_iso8601($message->created);

        $taguribase = common_config('integration', 'taguri');

        $entry['id'] = "tag:$taguribase,:$entry[link]";
        $entry['updated'] = $entry['published'];
        $entry['author'] = $message->getFrom()->getBestName();

        # RSS Item specific
        $entry['description'] = $entry['content'];
        $entry['pubDate'] = common_date_rfc2822($message->created);
        $entry['guid'] = $entry['link'];

        return $entry;
    }

    function twitter_dmsg_array($message)
    {
        $twitter_dm = array();

        $from_profile = $message->getFrom();
        $to_profile = $message->getTo();

        $twitter_dm['id'] = $message->id;
        $twitter_dm['sender_id'] = $message->from_profile;
        $twitter_dm['text'] = trim($message->content);
        $twitter_dm['recipient_id'] = $message->to_profile;
        $twitter_dm['created_at'] = $this->date_twitter($message->created);
        $twitter_dm['sender_screen_name'] = $from_profile->nickname;
        $twitter_dm['recipient_screen_name'] = $to_profile->nickname;
        $twitter_dm['sender'] = $this->twitter_user_array($from_profile, false);
        $twitter_dm['recipient'] = $this->twitter_user_array($to_profile, false);

        return $twitter_dm;
    }

    function show_twitter_xml_status($twitter_status)
    {
        $this->elementStart('status');
        foreach($twitter_status as $element => $value) {
            switch ($element) {
            case 'user':
                $this->show_twitter_xml_user($twitter_status['user']);
                break;
            case 'text':
                $this->element($element, null, common_xml_safe_str($value));
                break;
            default:
                $this->element($element, null, $value);
            }
        }
        $this->elementEnd('status');
    }

    function show_twitter_xml_user($twitter_user, $role='user')
    {
        $this->elementStart($role);
        foreach($twitter_user as $element => $value) {
            if ($element == 'status') {
                $this->show_twitter_xml_status($twitter_user['status']);
            } else {
                $this->element($element, null, $value);
            }
        }
        $this->elementEnd($role);
    }

    function show_twitter_rss_item($entry)
    {
        $this->elementStart('item');
        $this->element('title', null, $entry['title']);
        $this->element('description', null, $entry['description']);
        $this->element('pubDate', null, $entry['pubDate']);
        $this->element('guid', null, $entry['guid']);
        $this->element('link', null, $entry['link']);
        $this->elementEnd('item');
    }

    function show_json_objects($objects)
    {
        print(json_encode($objects));
    }

    function show_single_xml_status($notice)
    {
        $this->init_document('xml');
        $twitter_status = $this->twitter_status_array($notice);
        $this->show_twitter_xml_status($twitter_status);
        $this->end_document('xml');
    }

    function show_single_json_status($notice)
    {
        $this->init_document('json');
        $status = $this->twitter_status_array($notice);
        $this->show_json_objects($status);
        $this->end_document('json');
    }

    function show_single_xml_dmsg($message)
    {
        $this->init_document('xml');
        $dmsg = $this->twitter_dmsg_array($message);
        $this->show_twitter_xml_dmsg($dmsg);
        $this->end_document('xml');
    }

    function show_single_json_dmsg($message)
    {
        $this->init_document('json');
        $dmsg = $this->twitter_dmsg_array($message);
        $this->show_json_objects($dmsg);
        $this->end_document('json');
    }

    function show_twitter_xml_dmsg($twitter_dm)
    {
        $this->elementStart('direct_message');
        foreach($twitter_dm as $element => $value) {
            switch ($element) {
            case 'sender':
            case 'recipient':
                $this->show_twitter_xml_user($value, $element);
                break;
            case 'text':
                $this->element($element, null, common_xml_safe_str($value));
                break;
            default:
                $this->element($element, null, $value);
            }
        }
        $this->elementEnd('direct_message');
    }

    function show_xml_timeline($notice)
    {

        $this->init_document('xml');
        $this->elementStart('statuses', array('type' => 'array'));

        if (is_array($notice)) {
            foreach ($notice as $n) {
                $twitter_status = $this->twitter_status_array($n);
                $this->show_twitter_xml_status($twitter_status);
            }
        } else {
            while ($notice->fetch()) {
                $twitter_status = $this->twitter_status_array($notice);
                $this->show_twitter_xml_status($twitter_status);
            }
        }

        $this->elementEnd('statuses');
        $this->end_document('xml');
    }

    function show_rss_timeline($notice, $title, $link, $subtitle, $suplink=null)
    {

        $this->init_document('rss');

        $this->elementStart('channel');
        $this->element('title', null, $title);
        $this->element('link', null, $link);
        if (!is_null($suplink)) {
            # For FriendFeed's SUP protocol
            $this->element('link', array('xmlns' => 'http://www.w3.org/2005/Atom',
                                         'rel' => 'http://api.friendfeed.com/2008/03#sup',
                                         'href' => $suplink,
                                         'type' => 'application/json'));
        }
        $this->element('description', null, $subtitle);
        $this->element('language', null, 'en-us');
        $this->element('ttl', null, '40');

        if (is_array($notice)) {
            foreach ($notice as $n) {
                $entry = $this->twitter_rss_entry_array($n);
                $this->show_twitter_rss_item($entry);
            }
        } else {
            while ($notice->fetch()) {
                $entry = $this->twitter_rss_entry_array($notice);
                $this->show_twitter_rss_item($entry);
            }
        }

        $this->elementEnd('channel');
        $this->end_twitter_rss();
    }

    function show_atom_timeline($notice, $title, $id, $link, $subtitle=null, $suplink=null, $selfuri=null)
    {

        $this->init_document('atom');

        $this->element('title', null, $title);
        $this->element('id', null, $id);
        $this->element('link', array('href' => $link, 'rel' => 'alternate', 'type' => 'text/html'), null);

        if (!is_null($suplink)) {
            # For FriendFeed's SUP protocol
            $this->element('link', array('rel' => 'http://api.friendfeed.com/2008/03#sup',
                                         'href' => $suplink,
                                         'type' => 'application/json'));
        }

        if (!is_null($selfuri)) {
            $this->element('link', array('href' => $selfuri,
                'rel' => 'self', 'type' => 'application/atom+xml'), null);
        }

        $this->element('updated', null, common_date_iso8601('now'));
        $this->element('subtitle', null, $subtitle);

        if (is_array($notice)) {
            foreach ($notice as $n) {
                $this->raw($n->asAtomEntry());
            }
        } else {
            while ($notice->fetch()) {
                $this->raw($notice->asAtomEntry());
            }
        }

        $this->end_document('atom');

    }

    function show_json_timeline($notice)
    {

        $this->init_document('json');

        $statuses = array();

        if (is_array($notice)) {
            foreach ($notice as $n) {
                $twitter_status = $this->twitter_status_array($n);
                array_push($statuses, $twitter_status);
            }
        } else {
            while ($notice->fetch()) {
                $twitter_status = $this->twitter_status_array($notice);
                array_push($statuses, $twitter_status);
            }
        }

        $this->show_json_objects($statuses);

        $this->end_document('json');
    }

    // Anyone know what date format this is?
    // Twitter's dates look like this: "Mon Jul 14 23:52:38 +0000 2008" -- Zach
    function date_twitter($dt)
    {
        $t = strtotime($dt);
        return date("D M d H:i:s O Y", $t);
    }

    // XXX: Candidate for a general utility method somewhere?
    function count_subscriptions($profile)
    {

        $count = 0;
        $sub = new Subscription();
        $sub->subscribed = $profile->id;

        $count = $sub->find();

        if ($count > 0) {
            return $count - 1;
        } else {
            return 0;
        }
    }

    function init_document($type='xml')
    {
        switch ($type) {
         case 'xml':
            header('Content-Type: application/xml; charset=utf-8');
            $this->startXML();
            break;
         case 'json':
            header('Content-Type: application/json; charset=utf-8');

            // Check for JSONP callback
            $callback = $this->arg('callback');
            if ($callback) {
                print $callback . '(';
            }
            break;
         case 'rss':
            header("Content-Type: application/rss+xml; charset=utf-8");
            $this->init_twitter_rss();
            break;
         case 'atom':
            header('Content-Type: application/atom+xml; charset=utf-8');
            $this->init_twitter_atom();
            break;
         default:
            $this->client_error(_('Not a supported data format.'));
            break;
        }

        return;
    }

    function end_document($type='xml')
    {
        switch ($type) {
         case 'xml':
            $this->endXML();
            break;
         case 'json':

            // Check for JSONP callback
            $callback = $this->arg('callback');
            if ($callback) {
                print ')';
            }
            break;
         case 'rss':
            $this->end_twitter_rss();
            break;
         case 'atom':
            $this->end_twitter_rss();
            break;
         default:
            $this->client_error(_('Not a supported data format.'));
            break;
        }
        return;
    }

    function client_error($msg, $code = 400, $content_type = 'json')
    {

        static $status = array(400 => 'Bad Request',
                               401 => 'Unauthorized',
                               402 => 'Payment Required',
                               403 => 'Forbidden',
                               404 => 'Not Found',
                               405 => 'Method Not Allowed',
                               406 => 'Not Acceptable',
                               407 => 'Proxy Authentication Required',
                               408 => 'Request Timeout',
                               409 => 'Conflict',
                               410 => 'Gone',
                               411 => 'Length Required',
                               412 => 'Precondition Failed',
                               413 => 'Request Entity Too Large',
                               414 => 'Request-URI Too Long',
                               415 => 'Unsupported Media Type',
                               416 => 'Requested Range Not Satisfiable',
                               417 => 'Expectation Failed');

        $action = $this->trimmed('action');

        common_debug("User error '$code' on '$action': $msg", __FILE__);

        if (!array_key_exists($code, $status)) {
            $code = 400;
        }

        $status_string = $status[$code];
        header('HTTP/1.1 '.$code.' '.$status_string);

        if ($content_type == 'xml') {
            $this->init_document('xml');
            $this->elementStart('hash');
            $this->element('error', null, $msg);
            $this->element('request', null, $_SERVER['REQUEST_URI']);
            $this->elementEnd('hash');
            $this->end_document('xml');
        } else {
            $this->init_document('json');
            $error_array = array('error' => $msg, 'request' => $_SERVER['REQUEST_URI']);
            print(json_encode($error_array));
            $this->end_document('json');
        }

    }

    function init_twitter_rss()
    {
        $this->startXML();
        $this->elementStart('rss', array('version' => '2.0'));
    }

    function end_twitter_rss()
    {
        $this->elementEnd('rss');
        $this->endXML();
    }

    function init_twitter_atom()
    {
        $this->startXML();
        // FIXME: don't hardcode the language here!
        $this->elementStart('feed', array('xmlns' => 'http://www.w3.org/2005/Atom',
                                          'xml:lang' => 'en-US',
                                          'xmlns:thr' => 'http://purl.org/syndication/thread/1.0'));
    }

    function end_twitter_atom()
    {
        $this->elementEnd('feed');
        $this->endXML();
    }

    function show_profile($profile, $content_type='xml', $notice=null)
    {
        $profile_array = $this->twitter_user_array($profile, true);
        switch ($content_type) {
         case 'xml':
            $this->show_twitter_xml_user($profile_array);
            break;
         case 'json':
            $this->show_json_objects($profile_array);
            break;
         default:
            $this->client_error(_('Not a supported data format.'));
            return;
        }
        return;
    }

    function get_user($id, $apidata=null)
    {
        if (!$id) {
            
            // Twitter supports these other ways of passing the user ID
            if (is_numeric($this->arg('id'))) {
                return User::staticGet($this->arg('id'));
            } else if ($this->arg('id')) {
                $nickname = common_canonical_nickname($this->arg('id'));
                return User::staticGet('nickname', $nickname);
            } else if ($this->arg('user_id')) {
                // This is to ensure that a non-numeric user_id still 
                // overrides screen_name even if it doesn't get used
                if (is_numeric($this->arg('user_id'))) {
                    return User::staticGet('id', $this->arg('user_id'));
                }
            } else if ($this->arg('screen_name')) {
                $nickname = common_canonical_nickname($this->arg('screen_name'));
                return User::staticGet('nickname', $nickname);
            } else {
                // Fall back to trying the currently authenticated user
                return $apidata['user'];
            }
            
        } else if (is_numeric($id)) {
            return User::staticGet($id);
        } else {
            $nickname = common_canonical_nickname($id);
            return User::staticGet('nickname', $nickname);
        }
    }

    function get_profile($id)
    {
        if (is_numeric($id)) {
            return Profile::staticGet($id);
        } else {
            $user = User::staticGet('nickname', $id);
            if ($user) {
                return $user->getProfile();
            } else {
                return null;
            }
        }
    }

    function source_link($source)
    {
        $source_name = _($source);
        switch ($source) {
         case 'web':
         case 'xmpp':
         case 'mail':
         case 'omb':
         case 'api':
            break;
         default:
            $ns = Notice_source::staticGet($source);
            if ($ns) {
                $source_name = '<a href="' . $ns->url . '">' . $ns->name . '</a>';
            }
            break;
        }
        return $source_name;
    }

}
