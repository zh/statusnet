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

class SettingsAction extends Action {

	function handle($args) {
		parent::handle($args);
		if (!common_logged_in()) {
			common_user_error(_t('Not logged in.'));
			return;
		}
		if ($this->arg('METHOD') == 'POST') {
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

	function show_message($msg, $success) {
		if ($msg) {
			common_element('div', ($success) ? 'success' : 'error',
						   $msg);
		}
	}
	
	function settings_menu() {
		common_element_start('ul', 'headmenu');
		common_menu_item(common_local_url('editprofile'),
						 _t('Profile'));
		common_menu_item(common_local_url('avatar'),
						 _t('Avatar'));
		common_menu_item(common_local_url('password'),
						 _t('Password'));
		common_element_end('ul');
	}
}
