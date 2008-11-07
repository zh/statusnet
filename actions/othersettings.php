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

class OthersettingsAction extends SettingsAction {

	function get_instructions() {
		return _('Manage various other options.');
	}

	function show_form($msg=NULL, $success=false) {
		$user = common_current_user();
		
		$this->form_header(_('Other Settings'), $msg, $success);
		
		common_element_start('form', array('method' => 'post',
										   'id' => 'othersettings',
										   'action' =>
										   common_local_url('othersettings')));
		common_hidden('token', common_session_token());

		common_element('h2', NULL, _('URL Auto-shortening'));
		
		$services = array(
			'' => 'None',
            'ur1.ca' => 'ur1.ca (free)',
            '2tu.ru' => '2tu.ru (free)',
            'ptiturl.com' => 'ptiturl.com',
			'tinyurl.com' => 'tinyurl.com',
			'is.gd' => 'is.gd',
			'snipr.com' => 'snipr.com',
			'metamark.net' => 'metamark.net'
		);
		
		common_dropdown('urlshorteningservice', _('Service'), $services, _('Shortening service to use when notices exceed the 140 character limit.'), FALSE, $user->urlshorteningservice);
		
		common_submit('save', _('Save'));
		
		common_element_end('form');
		common_show_footer();
	}

	function handle_post() {

		# CSRF protection
		$token = $this->trimmed('token');
		if (!$token || $token != common_session_token()) {
			$this->show_form(_('There was a problem with your session token. Try again, please.'));
			return;
		}

		if ($this->arg('save')) {
			$this->save_preferences();
		}else {
			$this->show_form(_('Unexpected form submission.'));
		}
	}

	function save_preferences() {

		$urlshorteningservice = $this->trimmed('urlshorteningservice');
		
		if (!is_null($urlshorteningservice) && strlen($urlshorteningservice) > 50) {
			$this->show_form(_('URL shortening service is too long (max 50 chars).'));
			return;
		}
		
		$user = common_current_user();

		assert(!is_null($user)); # should already be checked

		$user->query('BEGIN');

		$original = clone($user);

		$user->urlshorteningservice = $urlshorteningservice;

		$result = $user->update($original);

		if ($result === FALSE) {
			common_log_db_error($user, 'UPDATE', __FILE__);
			common_server_error(_('Couldn\'t update user.'));
			return;
		}

		$user->query('COMMIT');

		$this->show_form(_('Preferences saved.'), true);
	}
}
