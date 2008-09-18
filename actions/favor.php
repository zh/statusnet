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

require_once(INSTALLDIR.'/lib/mail.php');

class FavorAction extends Action {

	function handle($args) {
		parent::handle($args);

		if (!common_logged_in()) {
			common_user_error(_('Not logged in.'));
			return;
		}

		$user = common_current_user();

		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			common_redirect(common_local_url('showfavorites', array('nickname' => $user->nickname)));
			return;
		}

		# CSRF protection

		$token = $this->trimmed('token');
		if (!$token || $token != common_session_token()) {
			$this->client_error(_('There was a problem with your session token. Try again, please.'));
			return;
		}
		$id = $this->trimmed('notice');

		$notice = Notice::staticGet($id);

		if ($user->hasFave($notice)) {
			$this->client_error(_('This notice is already a favorite!'));
			return;
		}

		$fave = Fave::addNew($user, $notice);

		if (!$fave) {
			$this->server_error(_('Could not create favorite.'));
			return;
		}

		$this->notify($fave, $notice, $user);

		if ($this->boolean('ajax')) {
			common_start_html('text/xml');
			common_element_start('head');
			common_element('title', _('Disfavor'));
			common_element_end('head');
			common_element_start('body');
			common_disfavor_form($notice);
			common_element_end('body');
			common_element_end('html');
		} else {
			common_redirect(common_local_url('showfavorites',
											 array('nickname' => $user->nickname)));
		}
	}

	function notify($fave, $notice, $user) {
	    $other = User::staticGet('id', $notice->profile_id);
		if ($other) {
			if ($other->email && $other->emailnotifyfav) {
				$this->notify_mail($other, $user, $notice);
			}
			# XXX: notify by IM
			# XXX: notify by SMS
		}
	}

	function notify_mail($other, $user, $notice) {
		$profile = $user->getProfile();
		$bestname = $profile->getBestName();
		$subject = sprintf(_('%s added your notice as a favorite'), $bestname);
		$body = sprintf(_('%1$s just added your notice from %2$s as one of their favorites.\n\n' .
						  'In case you forgot, you can see the text of your notice here:\n\n' .
						  '%3$s\n\n' .
						  'You can see the list of %1$s\'s favorites here:\n\n' .
						  '%4$s\n\n' .
						  'Faithfully yours,\n' .
						  '%5$s\n'),
						$bestname,
						common_exact_date($notice->created),
						common_local_url('shownotice', array('notice' => $notice->id)),
						common_local_url('showfavorites', array('nickname' => $user->nickname)),
						common_config('site', 'name'));

		mail_to_user($other, $subject, $body);
	}
}