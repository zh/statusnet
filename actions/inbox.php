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

class InboxAction extends MailboxAction {
	
	function get_title($user, $page) {
		if ($page > 1) {
			$title = sprintf(_("Inbox for %s - page %d"), $user->nickname, $page);
		} else {
			$title = sprintf(_("Inbox for %s"), $user->nickname);
		}
	}
	
	function get_messages($user, $page) {
		$message = new Message();
		$message->to_profile = $user->id;
		$message->orderBy('created DESC, id DESC');
		$message->limit((($page-1)*MESSAGES_PER_PAGE), MESSAGES_PER_PAGE + 1);

		if ($message->find()) {
			return $message;
		} else {
			return NULL;
		}
	}
	
	function get_message_profile($message) {
		return $message->getFrom();
	}
}
