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

class ImsettingsAction extends SettingsAction {

	function show_top($arr) {
		$msg = $arr[0];
		$success = $arr[1];
		if ($msg) {
			$this->message($msg, $success);
		} else {
			common_element('div', 'instructions',
						   _t('You can send and receive notices through '.
							  'Jabber/GTalk instant messages. Configure '.
							  'your address and settings below.'));
		}
		$this->settings_menu();
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		common_show_header(_t('IM settings'), NULL, array($msg, $success),
						   array($this, 'show_top'));

		common_element_start('form', array('method' => 'POST',
										   'id' => 'imsettings',
										   'action' =>
										   common_local_url('imsettings')));
		# too much common patterns here... abstractable?
		common_input('jabber', _t('IM Address'),
					 ($this->arg('jabber')) ? $this->arg('jabber') : $user->jabber,
					 _t('Jabber or GTalk address, like "UserName@example.org"'));
		common_checkbox('jabbernotify',
		                _t('Send me notices through Jabber/GTalk.'));
		common_checkbox('updatefrompresence',
		                _t('Post a notice when my Jabber/GTalk status changes.'));
		common_submit('submit', _t('Save'));
		common_element_end('form');
		common_show_footer();
	}

	function handle_post() {

		$jabber = jabber_normalize_jid($this->trimmed('jabber'));
		$jabbernotify = $this->boolean('jabbernotify');
		$updatefrompresence = $this->boolean('updatefrompresence');

		if (!jabber_valid_base_jid($jabber)) {
			$this->show_form(_('Not a valid Jabber ID'));
			return;
		} else if ($this->jabber_exists($jabber)) {
			$this->show_form(_('Not a valid Jabber ID'));
			return;
		}

		# Some validation

		$user = common_current_user();

		assert(!is_null($user)); # should already be checked

		$user->query('BEGIN');

		$original = clone($user);

		$user->jabber = $jabber;
		$user->jabbernotify = $jabbernotify;
		$user->updatefrompresence = $updatefrompresence;

		$result = $user->updateKeys($original); # For key columns

		if ($result === FALSE) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_t('Couldnt update user.'));
			return;
		}

		$result = $user->update($original); # For non-key columns

		if ($result === FALSE) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_t('Couldnt update user.'));
			return;
		}

		$user->query('COMMIT');

		$this->show_form(_t('Settings saved.'), TRUE);
	}

	function jabber_exists($jabber) {
		$user = common_current_user();
		$other = User::staticGet('jabber', $jabber);
		if (!$other) {
			return false;
		} else {
			return $other->id != $user->id;
		}
	}
}
