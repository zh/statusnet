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

class NewnoticeAction extends Action {

	function handle($args) {
		parent::handle($args);
		# XXX: Ajax!

		if (!common_logged_in()) {
			common_user_error(_t('Not logged in.'));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->save_new_notice();
		} else {
			$this->show_form();
		}
	}

	function save_new_notice() {

		$user = common_current_user();
		assert($user); # XXX: maybe an error instead...
		$notice = DB_DataObject::factory('notice');
		assert($notice);
		$notice->profile_id = $user->id; # user id *is* profile id
		$notice->created = DB_DataObject_Cast::dateTime();
		# Default theme uses 'content' for something else
		$notice->content = $this->trimmed('status_textarea');

		if (!$notice->content) {
			$this->show_form(_t('No content!'));
			return;
		} else if (strlen($notice->content) > 140) {
			$this->show_form(_t('Notice content too long.'));
			return;
		}

		$id = $notice->insert();
		
		if (!$id) {
			common_server_error(_t('Problem saving notice.'));
			return;
		}

		$orig = clone($notice);
		$notice->uri = common_mint_tag('notice:' . $id);
		
		if (!$notice->update($orig)) {
			common_server_error(_t('Problem saving notice.'));
			return;
		}
		
		common_broadcast_notice($notice);
		common_redirect(common_local_url('shownotice',
										 array('notice' => $id)), 303);
	}

	function show_form($msg=NULL) {
		common_show_header(_t('New notice'));
		if ($msg) {
			common_element('div', 'error', $msg);
		}
		common_notice_form();
		common_show_footer();
	}
}