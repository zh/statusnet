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

if (!defined('LACONICA')) { exit(1) }

class ProfilesettingsAction extends SettingsAction {
	
	function show_form($msg=NULL, $success=false) {
		common_show_header(_t('Profile settings'));
		$this->settings_menu();
		$this->message($msg, $success);
		common_start_element('form', array('method' => 'POST',
										   'id' => 'profilesettings',
										   'action' => 
										   common_local_url('profilesettings')));
		common_input('nickname', _t('Nickname'));
		common_input('fullname', _t('Full name'));
		common_input('email', _t('Email address'));		
		common_input('homepage', _t('Homepage'));				
		common_input('bio', _t('Bio'));
		common_input('location', _t('Location'));
		common_element('input', array('name' => 'submit',
									  'type' => 'submit',
									  'id' => 'submit'),
					   _t('Login'));
		common_element('input', array('name' => 'cancel',
									  'type' => 'button',
									  'id' => 'cancel'),
					   _t('Cancel'));
		common_show_footer();
	}
	
	function handle_post() {
		$nickname = $this->arg('nickname');
		$fullname = $this->arg('fullname');
		$email = $this->arg('email');
		$homepage = $this->arg('homepage');
		$bio = $this->arg('bio');
		$location = $this->arg('location');

		$user = common_current_user();
		assert(!is_null($user)); # should already be checked
		
		# FIXME: scrub input
		# FIXME: transaction!
		
		$user->nickname = $this->arg('nickname');
		$user->email = $this->arg('email');
		
		if (!$user->update()) {
			common_server_error(_t('Couldnt update user.'));
			return;
		}

		$profile = $user->getProfile();

		$profile->nickname = $user->nickname;
		$profile->fullname = $this->arg('fullname');
		$profile->homepage = $this->arg('homepage');
		$profile->bio = $this->arg('bio');
		$profile->location = $this->arg('location');

		if (!$profile->update()) {
			common_server_error(_t('Couldnt save profile.'));
			return;
		}
		
		$this->show_form(_t('Settings saved.'), TRUE);
	}
}