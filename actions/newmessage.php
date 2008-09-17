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

class NewmessageAction extends Action {
	
	function handle($args) {
		parent::handle($args);

		if (!common_logged_in()) {
			$this->client_error(_('Not logged in.'), 403);
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->save_new_message();
		} else {
			$this->show_form();
		}
	}

	function save_new_message() {

		$user = common_current_user();
		assert($user); # XXX: maybe an error instead...
		$content = $this->trimmed('content');
		$to = $this->trimmed('to');
		
		if (!$content) {
			$this->show_form(_('No content!'));
			return;
		} else if (mb_strlen($content) > 140) {
			common_debug("Content = '$content'", __FILE__);
			common_debug("mb_strlen(\$content) = " . mb_strlen($content), __FILE__);
			$this->show_form(_('That\'s too long. Max message size is 140 chars.'));
			return;
		}

		$other = User::staticGet('id', $to);
		
		if (!$other) {
			$this->show_form(_('No recipient specified.'));
			return;
		} else if (!$user->mutuallySubscribed($other)) {
			$this->client_error(_('You can\'t send a message to this user.'), 404);
			return;
		}
		
		$message = Message::saveNew($user->id, $other->id, $content, 'web');
		
		if (is_string($message)) {
			$this->show_form($message);
			return;
		}

		$this->notify($user, $to, $message);

		$url = common_local_url('showmessage',
								array('message' => $message->id));

		common_redirect($url, 303);
	}

	function show_top($params) {

		list($content, $user, $to) = $params;
		
		assert(!is_null($user));
		
		common_element_start('form', array('id' => 'message_form',
										   'method' => 'post',
										   'action' => $this->self_url()));
		
		common_element_start('p');
		
		$mutual_users = $user->mutuallySubscribedUsers();
		
		$mutual = array();
		
		while ($mutual_users->fetch()) {
			$mutual[$mutual_users->id] = $mutual_users->nickname;
		}

		$mutual_users->free();
		unset($mutual_users);

		common_dropdown('to', _('To'), $mutual,
						_('User you want to send a message to'), FALSE,
						$to->id);
		
		common_element('textarea', array('id' => 'content',
										 'cols' => 60,
										 'rows' => 3,
										 'name' => 'content'),
					   ($content) ? $content : '');
						
		common_element('input', array('id' => 'message_send',
									  'name' => 'message_send',
									  'type' => 'submit',
									  'value' => _('Send')));
		
		common_element_end('p');
		common_element_end('form');
	}

	function show_form($msg=NULL) {
		
		$content = $this->trimmed('content');
		$user = common_current_user();

		$to = common_canonical_nickname($this->trimmed('to'));
		
		$other = User::staticGet('nickname', $to);

		if (!$other) {
			$this->client_error(_('No such user'), 404);
			return;
		}

		if (!$user->mutuallySubscribed($other)) {
			$this->client_error(_('You can\'t send a message to this user.'), 404);
			return;
		}
		
		common_show_header(_('New message'), NULL,
						   array($content, $user, $to),
		                   array($this, 'show_top'));
		
		if ($msg) {
			common_element('p', 'error', $msg);
		}
		
		common_show_footer();
	}
	
	function notify($from, $to, $message) {
		mail_notify_message($message, $from, $to);
		# XXX: Jabber, SMS notifications... probably queued
	}
}
