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

class LoginAction extends Action {

	function handle($args) {
		parent::handle($args);
		if (common_logged_in()) {
			common_user_error(_t('Already logged in.'));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->check_login();
		} else {
			$this->show_form();
		}
	}

	function check_login() {
		# XXX: form token in $_SESSION to prevent XSS
		# XXX: login throttle
		$nickname = $this->arg('nickname');
		$password = $this->arg('password');
		if (common_check_user($nickname, $password)) {
			# success!
			if (!common_set_user($nickname)) {
				common_server_error(_t('Error setting user.'));
				return;
			}
			# success!
			$url = common_get_returnto();
			if ($url) {
				# We don't have to return to it again
				common_set_returnto(NULL);
			} else {
				$url = common_local_url('all',
										array('nickname' =>
											  $nickname));
			}
			common_redirect($url);
		} else {
			$this->show_form(_t('Incorrect username or password.'));
		}
	}

	function show_form($error=NULL) {
		common_show_header(_t('Login'));
		if (!is_null($error)) {
			common_element('div', array('class' => 'error'), $msg);
		}
		common_element_start('form', array('method' => 'POST',
										   'id' => 'login',
										   'action' => common_local_url('login')));
		common_input('nickname', _t('Nickname'));
		common_password('password', _t('Password'));
		common_submit('submit', _t('Login'));
		common_element_end('form');
		common_show_footer();
	}
}
