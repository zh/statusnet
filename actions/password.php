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

require_once(INSTALLDIR.'/lib/settingsaction.php');

class PasswordAction extends SettingsAction {

	function get_instructions() {
		return _('You can change your password here. Choose a good one!');
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		$this->form_header(_('Change password'), $msg, $success);
		common_element_start('form', array('method' => 'post',
										   'id' => 'password',
										   'action' =>
										   common_local_url('password')));
		# Users who logged in with OpenID won't have a pwd
		if ($user->password) {
			common_password('oldpassword', _('Old password'));
		}
		common_password('newpassword', _('New password'),
						_('6 or more characters'));
		common_password('confirm', _('Confirm'),
						_('same as password above'));
		common_submit('submit', _('Change'));
		common_element_end('form');
		common_show_footer();
	}

	function handle_post() {

		$user = common_current_user();
		assert(!is_null($user)); # should already be checked

		# FIXME: scrub input

		$newpassword = $this->arg('newpassword');
		$confirm = $this->arg('confirm');

		if (0 != strcmp($newpassword, $confirm)) {
			$this->show_form(_('Passwords don\'t match.'));
			return;
		}

		if ($user->password) {
			$oldpassword = $this->arg('oldpassword');

			if (!common_check_user($user->nickname, $oldpassword)) {
				$this->show_form(_('Incorrect old password'));
				return;
			}
		}

		$original = clone($user);

		$user->password = common_munge_password($newpassword, $user->id);

		$val = $user->validate();
		if ($val !== TRUE) {
			$this->show_form(_('Error saving user; invalid.'));
			return;
		}

		if (!$user->update($original)) {
			common_server_error(_('Can\'t save new password.'));
			return;
		}

		$this->show_form(_('Password saved.'), true);
	}
}
