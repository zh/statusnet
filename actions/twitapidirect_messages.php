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

require_once(INSTALLDIR.'/lib/twitterapi.php');

class Twitapidirect_messagesAction extends TwitterapiAction
{

    function direct_messages($args, $apidata)
    {
        parent::handle($args);
        return $this->show_messages($args, $apidata, 'received');
    }

    function sent($args, $apidata)
    {
        parent::handle($args);
        return $this->show_messages($args, $apidata, 'sent');
    }

    function show_messages($args, $apidata, $type)
    {
        $user = $apidata['user'];

        $count = $this->arg('count');
        $since = $this->arg('since');
        $since_id = $this->arg('since_id');
        $max_id = $this->arg('max_id');

        $page = $this->arg('page');

        if (!$page) {
            $page = 1;
        }

        if (!$count) {
            $count = 20;
        }

        $message = new Message();

        $title = null;
        $subtitle = null;
        $link = null;
        $server = common_root_url();

        if ($type == 'received') {
            $message->to_profile = $user->id;
            $title = sprintf(_("Direct messages to %s"), $user->nickname);
            $subtitle = sprintf(_("All the direct messages sent to %s"), $user->nickname);
            $link = $server . $user->nickname . '/inbox';
        } else {
            $message->from_profile = $user->id;
            $title = _('Direct Messages You\'ve Sent');
            $subtitle = sprintf(_("All the direct messages sent from %s"), $user->nickname);
            $link = $server . $user->nickname . '/outbox';
        }

        if ($max_id) {
            $message->whereAdd("id <= $max_id");
        }

        if ($since_id) {
            $message->whereAdd("id > $since_id");
        }

        $since = strtotime($this->arg('since'));

        if ($since) {
            $d = date('Y-m-d H:i:s', $since);
            $message->whereAdd("created > '$d'");
        }

        $message->orderBy('created DESC, id DESC');
        $message->limit((($page-1)*20), $count);
        $message->find();

        switch($apidata['content-type']) {
         case 'xml':
            $this->show_xml_dmsgs($message);
            break;
         case 'rss':
            $this->show_rss_dmsgs($message, $title, $link, $subtitle);
            break;
         case 'atom':
            $selfuri = common_root_url() . 'api/direct_messages';
            $selfuri .= ($type == 'received') ? '.atom' : '/sent.atom';
            $taguribase = common_config('integration', 'taguri');

            if ($type == 'sent') {
                $id = "tag:$taguribase:SentDirectMessages:" . $user->id;
            } else {
                $id = "tag:$taguribase:DirectMessages:" . $user->id;
            }

            $this->show_atom_dmsgs($message, $title, $link, $subtitle, $selfuri, $id);
            break;
         case 'json':
            $this->show_json_dmsgs($message);
            break;
         default:
            $this->clientError(_('API method not found!'), $code = 404);
        }

    }

    // had to change this from "new" to "create" to avoid PHP reserved word
    function create($args, $apidata)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_('This method requires a POST.'), 400, $apidata['content-type']);
            return;
        }

        $user = $apidata['user'];
        $source = $this->trimmed('source');     // Not supported by Twitter.

        $reserved_sources = array('web', 'omb', 'mail', 'xmpp', 'api');
        if (!$source || in_array($source, $reserved_sources)) {
            $source = 'api';
        }

        $content = $this->trimmed('text');

        if (!$content) {
            $this->clientError(_('No message text!'), $code = 406, $apidata['content-type']);
        } else {
            $content_shortened = common_shorten_links($content);
            if (mb_strlen($content_shortened) > 140) {
                $this->clientError(_('That\'s too long. Max message size is 140 chars.'),
                    $code = 406, $apidata['content-type']);
                return;
            }
        }

        $other = $this->get_user($this->trimmed('user'));

        if (!$other) {
            $this->clientError(_('Recipient user not found.'), $code = 403, $apidata['content-type']);
            return;
        } else if (!$user->mutuallySubscribed($other)) {
            $this->clientError(_('Can\'t send direct messages to users who aren\'t your friend.'),
                $code = 403, $apidata['content-type']);
            return;
        } else if ($user->id == $other->id) {
            // Sending msgs to yourself is allowed by Twitter
            $this->clientError(_('Don\'t send a message to yourself; just say it to yourself quietly instead.'),
                $code = 403, $apidata['content-type']);
            return;
        }

        $message = Message::saveNew($user->id, $other->id,
            html_entity_decode($content, ENT_NOQUOTES, 'UTF-8'), $source);

        if (is_string($message)) {
            $this->serverError($message);
            return;
        }

        $this->notify($user, $other, $message);

        if ($apidata['content-type'] == 'xml') {
            $this->show_single_xml_dmsg($message);
        } elseif ($apidata['content-type'] == 'json') {
            $this->show_single_json_dmsg($message);
        }

    }

    function destroy($args, $apidata)
    {
        parent::handle($args);
        $this->serverError(_('API method under construction.'), $code=501);
    }

    function show_xml_dmsgs($message)
    {

        $this->init_document('xml');
        $this->elementStart('direct-messages', array('type' => 'array'));

        if (is_array($message)) {
            foreach ($message as $m) {
                $twitter_dm = $this->twitter_dmsg_array($m);
                $this->show_twitter_xml_dmsg($twitter_dm);
            }
        } else {
            while ($message->fetch()) {
                $twitter_dm = $this->twitter_dmsg_array($message);
                $this->show_twitter_xml_dmsg($twitter_dm);
            }
        }

        $this->elementEnd('direct-messages');
        $this->end_document('xml');

    }

    function show_json_dmsgs($message)
    {

        $this->init_document('json');

        $dmsgs = array();

        if (is_array($message)) {
            foreach ($message as $m) {
                $twitter_dm = $this->twitter_dmsg_array($m);
                array_push($dmsgs, $twitter_dm);
            }
        } else {
            while ($message->fetch()) {
                $twitter_dm = $this->twitter_dmsg_array($message);
                array_push($dmsgs, $twitter_dm);
            }
        }

        $this->show_json_objects($dmsgs);
        $this->end_document('json');

    }

    function show_rss_dmsgs($message, $title, $link, $subtitle)
    {

        $this->init_document('rss');

        $this->elementStart('channel');
        $this->element('title', null, $title);

        $this->element('link', null, $link);
        $this->element('description', null, $subtitle);
        $this->element('language', null, 'en-us');
        $this->element('ttl', null, '40');

        if (is_array($message)) {
            foreach ($message as $m) {
                $entry = $this->twitter_rss_dmsg_array($m);
                $this->show_twitter_rss_item($entry);
            }
        } else {
            while ($message->fetch()) {
                $entry = $this->twitter_rss_dmsg_array($message);
                $this->show_twitter_rss_item($entry);
            }
        }

        $this->elementEnd('channel');
        $this->end_twitter_rss();

    }

    function show_atom_dmsgs($message, $title, $link, $subtitle, $selfuri, $id)
    {

        $this->init_document('atom');

        $this->element('title', null, $title);
        $this->element('id', null, $id);
        $this->element('link', array('href' => $link, 'rel' => 'alternate', 'type' => 'text/html'), null);
        $this->element('link', array('href' => $selfuri, 'rel' => 'self',
            'type' => 'application/atom+xml'), null);
        $this->element('updated', null, common_date_iso8601('now'));
        $this->element('subtitle', null, $subtitle);

        if (is_array($message)) {
            foreach ($message as $m) {
                $entry = $this->twitter_rss_dmsg_array($m);
                $this->show_twitter_atom_entry($entry);
            }
        } else {
            while ($message->fetch()) {
                $entry = $this->twitter_rss_dmsg_array($message);
                $this->show_twitter_atom_entry($entry);
            }
        }

        $this->end_document('atom');
    }

    // swiped from MessageAction. Should it be place in util.php?
    function notify($from, $to, $message)
    {
        mail_notify_message($message, $from, $to);
        # XXX: Jabber, SMS notifications... probably queued
    }

}
