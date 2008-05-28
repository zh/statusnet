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

class RegisterAction extends Action {

	function handle($args) {
		parent::handle($args);

		if (common_logged_in()) {
			common_user_error(_t('Already logged in.'));
		} else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->try_register();
		} else {
			$this->show_form();
		}
	}

	function try_register() {
		$nickname = $this->trimmed('nickname');
		$email = $this->trimmed('email');
		
		# We don't trim these... whitespace is OK in a password!
		
		$password = $this->arg('password');
		$confirm = $this->arg('confirm');

		# Input scrubbing

		$nickname = common_canonical_nickname($nickname);
		$email = common_canonical_email($email);

		if (!$this->boolean('license')) {
			$this->show_form(_t('You can\'t register if you don\'t agree to the license.'));
		} else if (!Validate::email($email, true)) {
			$this->show_form(_t('Not a valid email address.'));
		} else if (!Validate::string($nickname, array('min_length' => 1,
													  'max_length' => 64,
													  'format' => VALIDATE_NUM . VALIDATE_ALPHA_LOWER))) {
			$this->show_form(_t('Nickname must have only letters and numbers and no spaces.'));
		} else if ($this->nickname_exists($nickname)) {
			$this->show_form(_t('Nickname already exists.'));
		} else if ($this->email_exists($email)) {
			$this->show_form(_t('Email address already exists.'));
		} else if ($password != $confirm) {
			$this->show_form(_t('Passwords don\'t match.'));
		} else if ($this->register_user($nickname, $password, $email)) {
			# success!
			if (!common_set_user($nickname)) {
				common_server_error(_t('Error setting user.'));
				return;
			}
			common_redirect(common_local_url('profilesettings'));
		} else {
			$this->show_form(_t('Invalid username or password.'));
		}
	}

	# checks if *CANONICAL* nickname exists

	function nickname_exists($nickname) {
		$user = User::staticGet('nickname', $nickname);
		return ($user !== false);
	}

	# checks if *CANONICAL* email exists

	function email_exists($email) {
		$email = common_canonical_email($email);
		$user = User::staticGet('email', $email);
		return ($user !== false);
	}

	function register_user($nickname, $password, $email) {
		# TODO: wrap this in a transaction!
		$profile = new Profile();
		$profile->nickname = $nickname;
		$profile->profileurl = common_profile_url($nickname);
		$profile->created = DB_DataObject_Cast::dateTime(); # current time

		$id = $profile->insert();
		if (!$id) {
			return FALSE;
		}
		$user = new User();
		$user->id = $id;
		$user->nickname = $nickname;
		$user->password = common_munge_password($password, $id);
		$user->email = $email;
		$user->created =  DB_DataObject_Cast::dateTime(); # current time
		$user->uri = common_mint_tag('user:'.$id);
		
		$result = $user->insert();
		if (!$result) {
			# Try to clean up...
			$profile->delete();
		}
		return $result;
	}

	function show_form($error=NULL) {
		global $config;
		
		common_show_header(_t('Register'));
		common_element_start('form', array('method' => 'POST',
										   'id' => 'login',
										   'action' => common_local_url('register')));
		common_input('nickname', _t('Nickname'));
		common_password('password', _t('Password'));
		common_password('confirm', _t('Confirm'));
		common_input('email', _t('Email'));
		common_element_start('p');
		common_element('input', array('type' => 'checkbox',
									  'id' => 'license',
									  'name' => 'license',
									  'value' => 'true'));
		common_element_start('label', array('for' => 'license'));
		common_text(_t('My text and files are available under '));
		common_element('a', array(href => $config['license']['url']),
					   $config['license']['title']);
		common_text(_t(' except this private data: password, email address, IM address, phone number.'));
		common_element_end('label');
		common_element_end('p');
		common_submit('submit', _t('Register'));
		common_element_end('form');
		common_show_footer();
	}
}
