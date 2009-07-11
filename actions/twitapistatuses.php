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

require_once(INSTALLDIR.'/lib/twitterapi.php');

class TwitapistatusesAction extends TwitterapiAction
{

    function public_timeline($args, $apidata)
    {
        // XXX: To really live up to the spec we need to build a list
        // of notices by users who have custom avatars, so fix this SQL -- Zach

        parent::handle($args);

        $sitename   = common_config('site', 'name');
        $title      = sprintf(_("%s public timeline"), $sitename);
        $taguribase = common_config('integration', 'taguri');
        $id         = "tag:$taguribase:PublicTimeline";
        $link       = common_root_url();
        $subtitle   = sprintf(_("%s updates from everyone!"), $sitename);

        $page     = (int)$this->arg('page', 1);
        $count    = (int)$this->arg('count', 20);
        $max_id   = (int)$this->arg('max_id', 0);
        $since_id = (int)$this->arg('since_id', 0);
        $since    = $this->arg('since');

        $notice = Notice::publicStream(($page-1)*$count, $count, $since_id,
            $max_id, $since);

        switch($apidata['content-type']) {
        case 'xml':
            $this->show_xml_timeline($notice);
            break;
        case 'rss':
            $this->show_rss_timeline($notice, $title, $link, $subtitle);
            break;
        case 'atom':
            $selfuri = common_root_url() . 'api/statuses/public_timeline.atom';
            $this->show_atom_timeline($notice, $title, $id, $link,
                $subtitle, null, $selfuri);
            break;
        case 'json':
            $this->show_json_timeline($notice);
            break;
        default:
            $this->clientError(_('API method not found!'), $code = 404);
            break;
        }

    }

    function friends_timeline($args, $apidata)
    {
        parent::handle($args);

        $this->auth_user = $apidata['user'];
        $user = $this->get_user($apidata['api_arg'], $apidata);

        if (empty($user)) {
             $this->clientError(_('No such user!'), 404,
             $apidata['content-type']);
            return;
        }

        $profile    = $user->getProfile();
        $sitename   = common_config('site', 'name');
        $title      = sprintf(_("%s and friends"), $user->nickname);
        $taguribase = common_config('integration', 'taguri');
        $id         = "tag:$taguribase:FriendsTimeline:" . $user->id;
        $link       = common_local_url('all',
            array('nickname' => $user->nickname));
        $subtitle   = sprintf(_('Updates from %1$s and friends on %2$s!'),
            $user->nickname, $sitename);

        $page     = (int)$this->arg('page', 1);
        $count    = (int)$this->arg('count', 20);
        $max_id   = (int)$this->arg('max_id', 0);
        $since_id = (int)$this->arg('since_id', 0);
        $since    = $this->arg('since');

        if (!empty($this->auth_user) && $this->auth_user->id == $user->id) {
            $notice = $user->noticeInbox(($page-1)*$count,
                $count, $since_id, $max_id, $since);
        } else {
            $notice = $user->noticesWithFriends(($page-1)*$count,
                $count, $since_id, $max_id, $since);
        }

        switch($apidata['content-type']) {
        case 'xml':
            $this->show_xml_timeline($notice);
            break;
        case 'rss':
            $this->show_rss_timeline($notice, $title, $link, $subtitle);
            break;
        case 'atom':
            if (isset($apidata['api_arg'])) {
                $selfuri = common_root_url() .
                    'api/statuses/friends_timeline/' .
                        $apidata['api_arg'] . '.atom';
            } else {
                $selfuri = common_root_url() .
                    'api/statuses/friends_timeline.atom';
            }
            $this->show_atom_timeline($notice, $title, $id, $link,
                $subtitle, null, $selfuri);
            break;
        case 'json':
            $this->show_json_timeline($notice);
            break;
        default:
            $this->clientError(_('API method not found!'), $code = 404);
        }

    }

    function user_timeline($args, $apidata)
    {
        parent::handle($args);

        $this->auth_user = $apidata['user'];
        $user = $this->get_user($apidata['api_arg'], $apidata);

        if (empty($user)) {
            $this->clientError('Not Found', 404, $apidata['content-type']);
            return;
        }

        $profile = $user->getProfile();

        $sitename   = common_config('site', 'name');
        $title      = sprintf(_("%s timeline"), $user->nickname);
        $taguribase = common_config('integration', 'taguri');
        $id         = "tag:$taguribase:UserTimeline:".$user->id;
        $link       = common_local_url('showstream',
            array('nickname' => $user->nickname));
        $subtitle   = sprintf(_('Updates from %1$s on %2$s!'),
            $user->nickname, $sitename);

        # FriendFeed's SUP protocol
        # Also added RSS and Atom feeds

        $suplink = common_local_url('sup', null, null, $user->id);
        header('X-SUP-ID: '.$suplink);

        $page     = (int)$this->arg('page', 1);
        $count    = (int)$this->arg('count', 20);
        $max_id   = (int)$this->arg('max_id', 0);
        $since_id = (int)$this->arg('since_id', 0);
        $since    = $this->arg('since');

        $notice = $user->getNotices(($page-1)*$count,
            $count, $since_id, $max_id, $since);

        switch($apidata['content-type']) {
         case 'xml':
            $this->show_xml_timeline($notice);
            break;
         case 'rss':
            $this->show_rss_timeline($notice, $title, $link,
                $subtitle, $suplink);
            break;
         case 'atom':
            if (isset($apidata['api_arg'])) {
                $selfuri = common_root_url() .
                    'api/statuses/user_timeline/' .
                        $apidata['api_arg'] . '.atom';
            } else {
                $selfuri = common_root_url() .
                 'api/statuses/user_timeline.atom';
            }
            $this->show_atom_timeline($notice, $title, $id, $link,
                $subtitle, $suplink, $selfuri);
            break;
         case 'json':
            $this->show_json_timeline($notice);
            break;
         default:
            $this->clientError(_('API method not found!'), $code = 404);
        }

    }

    function update($args, $apidata)
    {
        parent::handle($args);

        if (!in_array($apidata['content-type'], array('xml', 'json'))) {
            $this->clientError(_('API method not found!'), $code = 404);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_('This method requires a POST.'),
                400, $apidata['content-type']);
            return;
        }

        $user = $apidata['user'];  // Always the auth user

        $status = $this->trimmed('status');
        $source = $this->trimmed('source');
        $in_reply_to_status_id =
            intval($this->trimmed('in_reply_to_status_id'));
        $reserved_sources = array('web', 'omb', 'mail', 'xmpp', 'api');

        if (empty($source) || in_array($source, $reserved_sources)) {
            $source = 'api';
        }

        if (empty($status)) {

            // XXX: Note: In this case, Twitter simply returns '200 OK'
            // No error is given, but the status is not posted to the
            // user's timeline.     Seems bad.     Shouldn't we throw an
            // errror? -- Zach
            return;

        } else {

            $status_shortened = common_shorten_links($status);

            if (mb_strlen($status_shortened) > 140) {

                // XXX: Twitter truncates anything over 140, flags the status
                // as "truncated." Sending this error may screw up some clients
                // that assume Twitter will truncate for them.    Should we just
                // truncate too? -- Zach
                $this->clientError(_('That\'s too long. Max notice size is 140 chars.'),
                    $code = 406, $apidata['content-type']);
                return;
            }
        }

        // Check for commands
        $inter = new CommandInterpreter();
        $cmd = $inter->handle_command($user, $status_shortened);

        if ($cmd) {

            if ($this->supported($cmd)) {
                $cmd->execute(new Channel());
            }

            // cmd not supported?  Twitter just returns your latest status.
            // And, it returns your last status whether the cmd was successful
            // or not!
            $n = $user->getCurrentNotice();
            $apidata['api_arg'] = $n->id;
        } else {

            $reply_to = null;

            if ($in_reply_to_status_id) {

                // check whether notice actually exists
                $reply = Notice::staticGet($in_reply_to_status_id);

                if ($reply) {
                    $reply_to = $in_reply_to_status_id;
                } else {
                    $this->clientError(_('Not found'), $code = 404,
                        $apidata['content-type']);
                    return;
                }
            }

            $notice = Notice::saveNew($user->id,
                html_entity_decode($status, ENT_NOQUOTES, 'UTF-8'),
                    $source, 1, $reply_to);

            if (is_string($notice)) {
                $this->serverError($notice);
                return;
            }

            common_broadcast_notice($notice);
            $apidata['api_arg'] = $notice->id;
        }

        $this->show($args, $apidata);
    }

    function mentions($args, $apidata)
    {
        parent::handle($args);

        $user = $this->get_user($apidata['api_arg'], $apidata);
        $this->auth_user = $apidata['user'];

        if (empty($user)) {
             $this->clientError(_('No such user!'), 404,
                 $apidata['content-type']);
            return;
        }

        $profile = $user->getProfile();

        $sitename   = common_config('site', 'name');
        $title      = sprintf(_('%1$s / Updates mentioning %2$s'),
            $sitename, $user->nickname);
        $taguribase = common_config('integration', 'taguri');
        $id         = "tag:$taguribase:Mentions:".$user->id;
        $link       = common_local_url('replies',
            array('nickname' => $user->nickname));
        $subtitle   = sprintf(_('%1$s updates that reply to updates from %2$s / %3$s.'),
            $sitename, $user->nickname, $profile->getBestName());

        $page     = (int)$this->arg('page', 1);
        $count    = (int)$this->arg('count', 20);
        $max_id   = (int)$this->arg('max_id', 0);
        $since_id = (int)$this->arg('since_id', 0);
        $since    = $this->arg('since');

        $notice = $user->getReplies(($page-1)*$count,
            $count, $since_id, $max_id, $since);

        switch($apidata['content-type']) {
        case 'xml':
            $this->show_xml_timeline($notice);
            break;
        case 'rss':
            $this->show_rss_timeline($notice, $title, $link, $subtitle);
            break;
        case 'atom':
            $selfuri = common_root_url() .
                ltrim($_SERVER['QUERY_STRING'], 'p=');
            $this->show_atom_timeline($notice, $title, $id, $link, $subtitle,
                null, $selfuri);
            break;
        case 'json':
            $this->show_json_timeline($notice);
            break;
        default:
            $this->clientError(_('API method not found!'), $code = 404);
        }

    }

    function replies($args, $apidata)
    {
        call_user_func(array($this, 'mentions'), $args, $apidata);
    }

    function show($args, $apidata)
    {
        parent::handle($args);

        if (!in_array($apidata['content-type'], array('xml', 'json'))) {
            $this->clientError(_('API method not found!'), $code = 404);
            return;
        }

        // 'id' is an undocumented parameter in Twitter's API. Several
        // clients make use of it, so we support it too.

        // show.json?id=12345 takes precedence over /show/12345.json

        $this->auth_user = $apidata['user'];
        $notice_id       = $this->trimmed('id');

        if (empty($notice_id)) {
            $notice_id   = $apidata['api_arg'];
        }

        $notice          = Notice::staticGet((int)$notice_id);

        if ($notice) {
            if ($apidata['content-type'] == 'xml') {
                $this->show_single_xml_status($notice);
            } elseif ($apidata['content-type'] == 'json') {
                $this->show_single_json_status($notice);
            }
        } else {
            // XXX: Twitter just sets a 404 header and doens't bother
            // to return an err msg
            $this->clientError(_('No status with that ID found.'),
                404, $apidata['content-type']);
        }
    }

    function destroy($args, $apidata)
    {
        parent::handle($args);

        if (!in_array($apidata['content-type'], array('xml', 'json'))) {
            $this->clientError(_('API method not found!'), $code = 404);
            return;
        }

        // Check for RESTfulness
        if (!in_array($_SERVER['REQUEST_METHOD'], array('POST', 'DELETE'))) {
            // XXX: Twitter just prints the err msg, no XML / JSON.
            $this->clientError(_('This method requires a POST or DELETE.'),
                400, $apidata['content-type']);
            return;
        }

        $user      = $apidata['user']; // Always the auth user
        $notice_id = $apidata['api_arg'];
        $notice    = Notice::staticGet($notice_id);

        if (empty($notice)) {
            $this->clientError(_('No status found with that ID.'),
                404, $apidata['content-type']);
            return;
        }

        if ($user->id == $notice->profile_id) {
            $replies = new Reply;
            $replies->get('notice_id', $notice_id);
            $replies->delete();
            $notice->delete();

            if ($apidata['content-type'] == 'xml') {
                $this->show_single_xml_status($notice);
            } elseif ($apidata['content-type'] == 'json') {
                $this->show_single_json_status($notice);
            }
        } else {
            $this->clientError(_('You may not delete another user\'s status.'),
                403, $apidata['content-type']);
        }

    }

    function friends($args, $apidata)
    {
        parent::handle($args);
        return $this->subscriptions($apidata, 'subscribed', 'subscriber');
    }

    function friendsIDs($args, $apidata)
    {
        parent::handle($args);
        return $this->subscriptions($apidata, 'subscribed', 'subscriber', true);
    }

    function followers($args, $apidata)
    {
        parent::handle($args);
        return $this->subscriptions($apidata, 'subscriber', 'subscribed');
    }

    function followersIDs($args, $apidata)
    {
        parent::handle($args);
        return $this->subscriptions($apidata, 'subscriber', 'subscribed', true);
    }

    function subscriptions($apidata, $other_attr, $user_attr, $onlyIDs=false)
    {
        $this->auth_user = $apidata['user'];
        $user = $this->get_user($apidata['api_arg'], $apidata);

        if (empty($user)) {
            $this->clientError('Not Found', 404, $apidata['content-type']);
            return;
        }

        $profile = $user->getProfile();

        $sub = new Subscription();
        $sub->$user_attr = $profile->id;

        $sub->orderBy('created DESC');

        // Normally, page 100 friends at a time

        if (!$onlyIDs) {
            $page  = $this->arg('page', 1);
            $count = $this->arg('count', 100);
            $sub->limit(($page-1)*$count, $count);
        } else {

            // If we're just looking at IDs, return
            // ALL of them, unless the user specifies a page,
            // in which case, return 500 per page.

            $page = $this->arg('page');
            if (!empty($page)) {
                if ($page < 1) {
                    $page = 1;
                }
                $count = 500;
                $sub->limit(($page-1)*$count, $count);
            }
        }

        $others = array();

        if ($sub->find()) {
            while ($sub->fetch()) {
                $others[] = Profile::staticGet($sub->$other_attr);
            }
        } else {
            // user has no followers
        }

        $type = $apidata['content-type'];

        $this->init_document($type);

        if ($onlyIDs) {
            $this->showIDs($others, $type);
        } else {
            $this->show_profiles($others, $type);
        }

        $this->end_document($type);
    }

    function show_profiles($profiles, $type)
    {
        switch ($type) {
        case 'xml':
            $this->elementStart('users', array('type' => 'array'));
            foreach ($profiles as $profile) {
                $this->show_profile($profile);
            }
            $this->elementEnd('users');
            break;
        case 'json':
            $arrays = array();
            foreach ($profiles as $profile) {
                $arrays[] = $this->twitter_user_array($profile, true);
            }
            print json_encode($arrays);
            break;
        default:
            $this->clientError(_('unsupported file type'));
        }
    }

    function showIDs($profiles, $type)
    {
        switch ($type) {
        case 'xml':
            $this->elementStart('ids');
            foreach ($profiles as $profile) {
                $this->element('id', null, $profile->id);
            }
            $this->elementEnd('ids');
            break;
        case 'json':
            $ids = array();
            foreach ($profiles as $profile) {
                $ids[] = (int)$profile->id;
            }
            print json_encode($ids);
            break;
        default:
            $this->clientError(_('unsupported file type'));
        }
    }

    function featured($args, $apidata)
    {
        parent::handle($args);
        $this->serverError(_('API method under construction.'), $code=501);
    }

    function supported($cmd)
    {
        $cmdlist = array('MessageCommand', 'SubCommand', 'UnsubCommand',
            'FavCommand', 'OnCommand', 'OffCommand');

        if (in_array(get_class($cmd), $cmdlist)) {
            return true;
        }

        return false;
    }

}
