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

class DisfavorAction extends Action {

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

		$token = $this->trimmed('token');

		if (!$token || $token != common_session_token()) {
			$this->client_error(_('There was a problem with your session token. Try again, please.'));
			return;
		}

		$id = $this->trimmed('notice');

		$notice = Notice::staticGet($id);

		$fave = new Fave();
		$fave->user_id = $this->id;
		$fave->notice_id = $notice->id;
		if (!$fave->find(true)) {
			$this->client_error(_('This notice is not a favorite!'));
			return;
		}

		$result = $fave->delete();

		if (!$result) {
			common_log_db_error($fave, 'DELETE', __FILE__);
			$this->server_error(_('Could not delete favorite.'));
			return;
		}

		# XXX: ajax response

		common_redirect(common_local_url('showfavorites',
										 array('nickname' => $user->nickname)));
	}
}