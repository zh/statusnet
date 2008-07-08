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
		return _t('You can change your password here. Choose a good one!');
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		$this->form_header(_t('Change password'), $msg, $success);
		common_element_start('form', array('method' => 'post',
										   'id' => 'password',
										   'action' =>
										   common_local_url('password')));
		# Users who logged in with OpenID won't have a pwd
		if ($user->password) {
			common_password('oldpassword', _t('Old password'));
		}
		common_password('newpassword', _t('New password'),
						_t('6 or more characters'));
		common_password('confirm', _t('Confirm'),
						_t('same as password above'));
		common_submit('submit', _t('Change'));
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
			$this->show_form(_t('Passwords don\'t match'));
			return;
		}

		if ($user->password) {
			$oldpassword = $this->arg('oldpassword');
		
			if (!common_check_user($user->nickname, $oldpassword)) {
				$this->show_form(_t('Incorrect old password'));
				return;
			}
		}

		$original = clone($user);

		$user->password = common_munge_password($newpassword, $user->id);

		$val = $user->validate();
		if ($val !== TRUE) {
			$this->show_form(_t('Error saving user; invalid.'));
			return;
		}

		if (!$user->update($original)) {
			common_server_error(_t('Can\'t save new password.'));
			return;
		}

		$this->show_form(_t('Password saved'), true);
	}
}
