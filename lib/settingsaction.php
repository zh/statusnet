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

class SettingsAction extends Action {

	function handle($args) {
		parent::handle($args);
		if (!common_logged_in()) {
			common_user_error(_t('Not logged in.'));
			return;
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->handle_post();
		} else {
			$this->show_form();
		}
	}

	# override!
	function handle_post() {
		return false;
	}

	function show_form($msg=NULL, $success=false) {
		return false;
	}

	function message($msg, $success) {
		if ($msg) {
			common_element('div', ($success) ? 'success' : 'error',
						   $msg);
		}
	}

	function settings_menu() {
		$action = $this->trimmed('action');
		common_element_start('ul', array('id' => 'nav_views'));
		common_menu_item(common_local_url('profilesettings'),
						 _t('Profile'), 
						 _t('Change your profile settings'),
						 $action == 'profilesettings');
		common_menu_item(common_local_url('avatar'),
						 _t('Avatar'), 
						 _t('Upload a new profile image'),
						 $action == 'avatar');
		common_menu_item(common_local_url('password'),
						 _t('Password'), 
						 _t('Change your password'),
						 $action == 'password');
		common_menu_item(common_local_url('openidsettings'),
						 _t('OpenID'), 
						 _t('Add or remove OpenIDs'),
						 $action == 'openidsettings');
		if (false) {
			common_menu_item(common_local_url('emailsettings'),
							 _t('Email'),
							 _t('Address and preferences'),
							 $action == 'emailsettings');
			common_menu_item(common_local_url('imsettings'),
							 _t('IM'), 
							 _t('Notifications by instant messenger'),
							 $action == 'imsettings');
			common_menu_item(common_local_url('phonesettings'),
							 _t('Phone'), 
							 _t('Notifications by phone'),
							 $action == 'phonesettings');
		}
		common_element_end('ul');
	}
}
