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

class NudgeAction extends Action {

	function handle($args) {
		parent::handle($args);

		if (!common_logged_in()) {
			common_user_error(_('Not logged in.'));
			return;
		}

		$user = common_current_user();
		$other_nickname = common_canonical_nickname($args['nickname']);
		$other = User::staticGet('nickname', $other_nickname);
		$this->notify($user, $other);

		if ($this->boolean('ajax')) {
			common_start_html('text/xml');
			common_element_start('head');
			common_element('title', null, _('Nudge sent'));
			common_element_end('head');
			common_element_start('body');
			common_nudge_response();
			common_element_end('body');
			common_element_end('html');
		} else {
            // display a confirmation to the user
			common_redirect(common_local_url('showstream',
											 array('nickname' => $other->nickname)));
		}
	}

	function notify($user, $other) {
		if ($other->id != $user->id) {
			if ($other->email && $other->emailnotifynudge) {
				mail_notify_nudge($user, $other);
			}
			# XXX: notify by IM
			# XXX: notify by SMS
		}
	}
}

