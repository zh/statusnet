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

require_once(INSTALLDIR.'/lib/personal.php');

define('MESSAGES_PER_PAGE', 20);

class MailboxAction extends PersonalAction {
	
	function handle($args) {

		parent::handle($args);

		$nickname = common_canonical_nickname($this->arg('nickname'));
		$user = User::staticGet('nickname', $nickname);

		if (!$user) {
			$this->client_error(_('No such user.'), 404);
			return;
		}

		$cur = common_current_user();
		
		if (!$cur || $cur->id != $user->id) {
			$this->client_error(_('Only the user can read their own mailboxes.'), 403);
			return;
		}
		
		$profile = $user->getProfile();

		if (!$profile) {
			$this->server_error(_('User has no profile.'));
			return;
		}

		$page = $this->trimmed('page');
		
		if (!$page) {
			$page = 1;
		}
		
		$this->show_page($user, $page);
	}

	function get_title($user, $page) {
		return '';
	}

	function get_instructions() {
		return '';
	}

	function show_top() {

		$cur = common_current_user();
		
		common_message_form(NULL, $cur, NULL);
		
		$this->views_menu();
	}
	
	function show_page($user, $page) {

		common_show_header($this->get_title($user, $page),
						   NULL, NULL,
						   array($this, 'show_top'));
		
		$this->show_box($user, $page);
		
		common_show_footer();
	}
	
	function show_box($user, $page) {
		
		$message = $this->get_messages($user, $page);
		
		if ($message) {
			
			$cnt = 0;
			common_element_start('ul', array('id' => 'messages'));
		
			while ($message->fetch() && $cnt <= MESSAGES_PER_PAGE) {
				$cnt++;
				
				if ($cnt > MESSAGES_PER_PAGE) {
					break;
				}
				
				$this->show_message($message);
			}

			common_element_end('ul');
			
			common_pagination($page > 1, $cnt > MESSAGES_PER_PAGE,
							  $page, $this->trimmed('action'),
							  array('nickname' => $user->nickname));
			
			$message->free();
			unset($message);
		}
	}

	# returns the profile we want to show with the message
	
	function get_message_profile($message) {
		return NULL;
	}
	
	function show_message($message) {

		common_element_start('li', array('class' => 'message_single',
										  'id' => 'message-' . $message->id));

		$profile = $this->get_message_profile($message);
		
		$avatar = $profile->getAvatar(AVATAR_STREAM_SIZE);
		common_element_start('a', array('href' => $profile->profileurl));
		common_element('img', array('src' => ($avatar) ? common_avatar_display_url($avatar) : common_default_avatar(AVATAR_STREAM_SIZE),
									'class' => 'avatar stream',
									'width' => AVATAR_STREAM_SIZE,
									'height' => AVATAR_STREAM_SIZE,
									'alt' =>
									($profile->fullname) ? $profile->fullname :
									$profile->nickname));
		common_element_end('a');
		common_element('a', array('href' => $profile->profileurl,
								  'class' => 'nickname'),
					   $profile->nickname);
		# FIXME: URL, image, video, audio
		common_element_start('p', array('class' => 'content'));
		common_raw($message->rendered);
		common_element_end('p');
		
		$messageurl = common_local_url('showmessage', array('message' => $message->id));
		
		# XXX: we need to figure this out better. Is this right?
		if (strcmp($message->uri, $messageurl) != 0 && preg_match('/^http/', $message->uri)) {
			$messageurl = $message->uri;
		}
		common_element_start('p', 'time');
		common_element('a', array('class' => 'permalink',
								  'href' => $messageurl,
								  'title' => common_exact_date($message->created)),
					   common_date_string($message->created));
		if ($message->source) {
			common_text(_(' from '));
			$this->source_link($message->source);
		}
		
		common_element_end('p');
		
		common_element_end('li');
	}
}
