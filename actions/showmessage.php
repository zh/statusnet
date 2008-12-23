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

require_once(INSTALLDIR.'/lib/mailbox.php');

class ShowmessageAction extends MailboxAction {

    function handle($args) {

        Action::handle($args);

        $message = $this->get_message();

        if (!$message) {
            $this->client_error(_('No such message.'), 404);
            return;
        }
        
        $cur = common_current_user();
        
        if ($cur && ($cur->id == $message->from_profile || $cur->id == $message->to_profile)) {
            $this->show_page($cur, 1);
        } else {
            $this->client_error(_('Only the sender and recipient may read this message.'), 403);
            return;
        }
    }
    
    function get_message() {
        $id = $this->trimmed('message');
        $message = Message::staticGet('id', $id);
        return $message;
    }
    
    function get_title($user, $page) {
        $message = $this->get_message();
        if (!$message) {
            return null;
        }
        
        if ($user->id == $message->from_profile) {
            $to = $message->getTo();
            $title = sprintf(_("Message to %1\$s on %2\$s"),
                             $to->nickname,
                             common_exact_date($message->created));
        } else if ($user->id == $message->to_profile) {
            $from = $message->getFrom();
            $title = sprintf(_("Message from %1\$s on %2\$s"),
                             $from->nickname,
                             common_exact_date($message->created));
        }
        return $title;
    }

    function get_messages($user, $page) {
        $message = new Message();
        $message->id = $this->trimmed('message');
        $message->find();
        return $message;
    }
    
    function get_message_profile($message) {
        $user = common_current_user();
        if ($user->id == $message->from_profile) {
            return $message->getTo();
        } else if ($user->id == $message->to_profile) {
            return $message->getFrom();
        } else {
            # This shouldn't happen
            return null;
        }
    }
    
    function get_instructions() {
        return '';
    }
    
    function views_menu() {
        return;
    }
}
    