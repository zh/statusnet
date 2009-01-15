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

class NewmessageAction extends Action
{
    
    function handle($args)
    {
        parent::handle($args);

        if (!common_logged_in()) {
            $this->client_error(_('Not logged in.'), 403);
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->save_new_message();
        } else {
            $this->show_form();
        }
    }

    function save_new_message()
    {
        $user = common_current_user();
        assert($user); # XXX: maybe an error instead...

        # CSRF protection
        
        $token = $this->trimmed('token');
        if (!$token || $token != common_session_token()) {
            $this->show_form(_('There was a problem with your session token. Try again, please.'));
            return;
        }
        
        $content = $this->trimmed('content');
        $to = $this->trimmed('to');
        
        if (!$content) {
            $this->show_form(_('No content!'));
            return;
        } else {
            $content_shortened = common_shorten_links($content);

            if (mb_strlen($content_shortened) > 140) {
                common_debug("Content = '$content_shortened'", __FILE__);
                common_debug("mb_strlen(\$content) = " . mb_strlen($content_shortened), __FILE__);
                $this->show_form(_('That\'s too long. Max message size is 140 chars.'));
                return;
            }
        }

        $other = User::staticGet('id', $to);
        
        if (!$other) {
            $this->show_form(_('No recipient specified.'));
            return;
        } else if (!$user->mutuallySubscribed($other)) {
            $this->client_error(_('You can\'t send a message to this user.'), 404);
            return;
        } else if ($user->id == $other->id) {
            $this->client_error(_('Don\'t send a message to yourself; just say it to yourself quietly instead.'), 403);
            return;
        }
        
        $message = Message::saveNew($user->id, $other->id, $content, 'web');
        
        if (is_string($message)) {
            $this->show_form($message);
            return;
        }

        $this->notify($user, $other, $message);

        $url = common_local_url('outbox', array('nickname' => $user->nickname));

        common_redirect($url, 303);
    }

    function show_top($params)
    {

        list($content, $user, $to) = $params;
        
        assert(!is_null($user));

        common_message_form($content, $user, $to);
    }

    function show_form($msg=null)
    {
        
        $content = $this->trimmed('content');
        $user = common_current_user();

        $to = $this->trimmed('to');
        
        $other = User::staticGet('id', $to);

        if (!$other) {
            $this->client_error(_('No such user'), 404);
            return;
        }

        if (!$user->mutuallySubscribed($other)) {
            $this->client_error(_('You can\'t send a message to this user.'), 404);
            return;
        }
        
        common_show_header(_('New message'), null,
                           array($content, $user, $other),
                           array($this, 'show_top'));
        
        if ($msg) {
            $this->element('p', array('id'=>'error'), $msg);
        }
        
        common_show_footer();
    }
    
    function notify($from, $to, $message)
    {
        mail_notify_message($message, $from, $to);
        # XXX: Jabber, SMS notifications... probably queued
    }
}
