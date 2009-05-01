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

class TwitapifriendshipsAction extends TwitterapiAction
{

    function create($args, $apidata)
    {
        parent::handle($args);

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->clientError(_('This method requires a POST.'), 400, $apidata['content-type']);
            return;
        }

        $id = $apidata['api_arg'];

        $other = $this->get_user($id);

        if (!$other) {
            $this->clientError(_('Could not follow user: User not found.'), 403, $apidata['content-type']);
            return;
        }

        $user = $apidata['user'];

        if ($user->isSubscribed($other)) {
            $errmsg = sprintf(_('Could not follow user: %s is already on your list.'), $other->nickname);
            $this->clientError($errmsg, 403, $apidata['content-type']);
            return;
        }

        $sub = new Subscription();

        $sub->query('BEGIN');

        $sub->subscriber = $user->id;
        $sub->subscribed = $other->id;
        $sub->created = DB_DataObject_Cast::dateTime(); # current time

        $result = $sub->insert();

        if (!$result) {
            $errmsg = sprintf(_('Could not follow user: %s is already on your list.'), $other->nickname);
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
            $this->clientError(_('This method requires a POST or DELETE.'), 400, $apidata['content-type']);
            return;
        }

        $id = $apidata['api_arg'];

        # We can't subscribe to a remote person, but we can unsub

        $other = $this->get_profile($id);
        $user = $apidata['user'];

        $sub = new Subscription();
        $sub->subscriber = $user->id;
        $sub->subscribed = $other->id;

        if ($sub->find(true)) {
            $sub->query('BEGIN');
            $sub->delete();
            $sub->query('COMMIT');
        } else {
            $this->clientError(_('You are not friends with the specified user.'), 403, $apidata['content-type']);
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

        if (!$user_a || !$user_b) {
            $this->clientError(_('Two user ids or screen_names must be supplied.'), 400, $apidata['content-type']);
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

}