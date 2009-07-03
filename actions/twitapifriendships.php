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

class TwitapifriendshipsAction extends TwitterapiAction
{

    function create($args, $apidata)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_('This method requires a POST.'),
                400, $apidata['content-type']);
            return;
        }

        $id    = $apidata['api_arg'];
        $other = $this->get_user($id);

        if (empty($other)) {
            $this->clientError(_('Could not follow user: User not found.'),
                403, $apidata['content-type']);
            return;
        }

        $user = $apidata['user'];

        if ($user->isSubscribed($other)) {
            $errmsg = sprintf(_('Could not follow user: %s is already on your list.'),
                $other->nickname);
            $this->clientError($errmsg, 403, $apidata['content-type']);
            return;
        }

        $sub = new Subscription();

        $sub->query('BEGIN');

        $sub->subscriber = $user->id;
        $sub->subscribed = $other->id;
        $sub->created = DB_DataObject_Cast::dateTime(); # current time

        $result = $sub->insert();

        if (empty($result)) {
            $errmsg = sprintf(_('Could not follow user: %s is already on your list.'),
                $other->nickname);
            $this->clientError($errmsg, 400, $apidata['content-type']);
            return;
        }

        $sub->query('COMMIT');

        mail_subscribe_notify($other, $user);

        $type = $apidata['content-type'];
        $this->init_document($type);
        $this->show_profile($other, $type);
        $this->end_document($type);

    }

    function destroy($args, $apidata)
    {
        parent::handle($args);

        if (!in_array($_SERVER['REQUEST_METHOD'], array('POST', 'DELETE'))) {
            $this->clientError(_('This method requires a POST or DELETE.'),
                400, $apidata['content-type']);
            return;
        }

        $id = $apidata['api_arg'];

        # We can't subscribe to a remote person, but we can unsub

        $other = $this->get_profile($id);
        $user = $apidata['user']; // Alwyas the auth user

        $sub = new Subscription();
        $sub->subscriber = $user->id;
        $sub->subscribed = $other->id;

        if ($sub->find(true)) {
            $sub->query('BEGIN');
            $sub->delete();
            $sub->query('COMMIT');
        } else {
            $this->clientError(_('You are not friends with the specified user.'),
                403, $apidata['content-type']);
            return;
        }

        $type = $apidata['content-type'];
        $this->init_document($type);
        $this->show_profile($other, $type);
        $this->end_document($type);

    }

    function exists($args, $apidata)
    {
        parent::handle($args);

        if (!in_array($apidata['content-type'], array('xml', 'json'))) {
            $this->clientError(_('API method not found!'), $code = 404);
            return;
        }

        $user_a_id = $this->trimmed('user_a');
        $user_b_id = $this->trimmed('user_b');

        $user_a = $this->get_user($user_a_id);
        $user_b = $this->get_user($user_b_id);

        if (empty($user_a) || empty($user_b)) {
            $this->clientError(_('Two user ids or screen_names must be supplied.'),
                400, $apidata['content-type']);
            return;
        }

        $result = $user_a->isSubscribed($user_b);

        switch ($apidata['content-type']) {
         case 'xml':
            $this->init_document('xml');
            $this->element('friends', null, $result);
            $this->end_document('xml');
            break;
         case 'json':
            $this->init_document('json');
            print json_encode($result);
            $this->end_document('json');
            break;
         default:
            break;
        }

    }

    function show($args, $apidata)
    {
        parent::handle($args);

        if (!in_array($apidata['content-type'], array('xml', 'json'))) {
            $this->clientError(_('API method not found!'), $code = 404);
            return;
        }

        $source_id          = (int)$this->trimmed('source_id');
        $source_screen_name = $this->trimmed('source_screen_name');

        // If the source is not specified for an unauthenticated request,
        // the method will return an HTTP 403.

        if (empty($source_id) && empty($source_screen_name)) {
            if (empty($apidata['user'])) {
                $this->clientError(_('Could not determine source user.'),
                        $code = 403);
                return;
            }
        }

        $source = null;

        if (!empty($source_id)) {
            $source = User::staticGet($source_id);
        } elseif (!empty($source_screen_name)) {
            $source = User::staticGet('nickname', $source_screen_name);
        } else {
            $source = $apidata['user'];
        }

        // If a source or target is specified but does not exist,
        // the method will return an HTTP 404.

        if (empty($source)) {
            $this->clientError(_('Could not determine source user.'),
                $code = 404);
            return;
        }

        $target_id          = (int)$this->trimmed('target_id');
        $target_screen_name = $this->trimmed('target_screen_name');

        $target = null;

        if (!empty($target_id)) {
            $target = User::staticGet($target_id);
        } elseif (!empty($target_screen_name)) {
            $target = User::staticGet('nickname', $target_screen_name);
        } else {
            $this->clientError(_('Target user not specified.'),
                $code = 403);
            return;
        }

        if (empty($target)) {
            $this->clientError(_('Could not find target user.'),
                $code = 404);
            return;
        }

        $result = $this->twitter_relationship_array($source, $target);

        switch ($apidata['content-type']) {
        case 'xml':
            $this->init_document('xml');
            $this->show_twitter_xml_relationship($result[relationship]);
            $this->end_document('xml');
            break;
        case 'json':
            $this->init_document('json');
            print json_encode($result);
            $this->end_document('json');
            break;
        default:
            break;
        }
    }

}
